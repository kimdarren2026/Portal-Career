<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\VacancyModerationAction;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Exceptions\ReviewReasonRequired;
use App\Domains\Vacancy\Exceptions\VacancyCompanyNotVerified;
use App\Domains\Vacancy\Exceptions\VacancyInvalidTransition;
use App\Domains\Vacancy\Exceptions\VacancyModerationNotApplicable;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyLifecycleNotifier;
use Illuminate\Support\Facades\DB;

/**
 * The company vacancy moderation transitions (FR-VAC-006, FSD §8.3), each in
 * one transaction under the vacancy row lock: source check → reason check →
 * transition → append-only review row → audit → outbox.
 *
 * Targets are the approved deterministic ones:
 *  - APPROVE (B-1): SCHEDULED before open_at, PUBLISHED inside the window, and
 *    refusal at or after close_at — the vacancy then stays PENDING_REVIEW.
 *  - RESTORE (B-2): PUBLISHED before close_at, otherwise CLOSED with closed_at
 *    set now. `published_at` is preserved either way.
 *
 * `APPROVED` is never emitted by this flow; it remains in the vocabulary.
 */
final class ModerateVacancy
{
    public function __construct(
        private readonly PublishVacancy $publisher,
        private readonly AuditWriter $audit,
        private readonly VacancyLifecycleNotifier $notifier,
    ) {}

    /** @param array<string, mixed> $details */
    public function execute(User $actor, Vacancy $vacancy, VacancyModerationAction $action, array $details = []): Vacancy
    {
        return DB::transaction(function () use ($actor, $vacancy, $action, $details): Vacancy {
            /** @var Vacancy $locked */
            $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isCompanyOwned()) {
                throw new VacancyModerationNotApplicable('VACANCY_MODERATION_NOT_APPLICABLE');
            }

            // INV-029: adverse actions carry a category and a recruiter-visible note.
            if ($action->requiresReason()
                && (trim((string) ($details['reason_category'] ?? '')) === ''
                    || trim((string) ($details['recruiter_visible_note'] ?? '')) === '')) {
                throw new ReviewReasonRequired('REVIEW_REASON_REQUIRED');
            }

            $from = $locked->current_status;
            $to = $this->target($locked, $action);

            if ($action === VacancyModerationAction::Approve) {
                /** @var Company $company */
                $company = Company::query()->whereKey($locked->company_id)->lockForUpdate()->firstOrFail();
                if ($company->verification_status !== CompanyStatus::Verified) {
                    throw new VacancyCompanyNotVerified('VACANCY_COMPANY_NOT_VERIFIED');
                }
            }

            $locked->moderationReviews()->forceCreate([
                'reviewer_user_id' => $actor->getKey(),
                'action' => $action->value,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason_category' => $details['reason_category'] ?? null,
                'recruiter_visible_note' => $details['recruiter_visible_note'] ?? null,
                'internal_note' => $details['internal_note'] ?? null,
                'reviewed_at' => now(),
            ]);

            $this->audit->record($action->auditAction(), $actor, 'vacancy', (int) $locked->getKey(), [
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);

            // Immediate publication runs the same authoritative behaviour the
            // scheduler uses, which is what emits vacancy_published alongside
            // vacancy_approved exactly as the approve contract requires.
            if ($action === VacancyModerationAction::Approve && $to === VacancyStatus::Published) {
                $this->publisher->execute($locked, $actor);

                return $locked->refresh();
            }

            $locked->current_status = $to;
            if ($to === VacancyStatus::Suspended) {
                $locked->suspended_at = now();
            }
            if ($to === VacancyStatus::Closed) {
                $locked->closed_at = now();
            }
            // Leaving SUSPENDED clears the suspension marker, which the data
            // dictionary defines as "suspension time when applicable" and which
            // the frozen company-review precedent clears the same way. RESTORE
            // never rewrites published_at (INV-013, B-2).
            if ($action === VacancyModerationAction::Restore) {
                $locked->suspended_at = null;
            }
            $locked->updated_at = now();
            $locked->save();

            $this->notifier->queue($locked, $action, $details);

            return $locked->refresh();
        });
    }

    private function target(Vacancy $vacancy, VacancyModerationAction $action): VacancyStatus
    {
        $from = $vacancy->current_status;
        $now = now();

        return match ($action) {
            VacancyModerationAction::RequestRevision => $this->requireFrom($from, [VacancyStatus::PendingReview], VacancyStatus::RevisionRequired),
            VacancyModerationAction::Reject => $this->requireFrom($from, [VacancyStatus::PendingReview], VacancyStatus::Rejected),
            VacancyModerationAction::Suspend => $this->requireFrom($from, [VacancyStatus::Published], VacancyStatus::Suspended),
            VacancyModerationAction::Close => $this->requireFrom($from, [VacancyStatus::Published], VacancyStatus::Closed),
            VacancyModerationAction::Approve => $this->approveTarget($vacancy, $from, $now),
            VacancyModerationAction::Restore => $this->restoreTarget($vacancy, $from, $now),
            VacancyModerationAction::Submit => throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION'),
        };
    }

    /** B-1. */
    private function approveTarget(Vacancy $vacancy, VacancyStatus $from, \DateTimeInterface $now): VacancyStatus
    {
        if ($from !== VacancyStatus::PendingReview) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }
        if ($vacancy->open_at === null || $vacancy->close_at === null) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }
        // At or after close_at the vacancy can no longer be approved; it stays
        // PENDING_REVIEW and no state is written.
        if ($now >= $vacancy->close_at) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }

        return $now < $vacancy->open_at ? VacancyStatus::Scheduled : VacancyStatus::Published;
    }

    /** B-2. */
    private function restoreTarget(Vacancy $vacancy, VacancyStatus $from, \DateTimeInterface $now): VacancyStatus
    {
        if ($from !== VacancyStatus::Suspended) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }

        return ($vacancy->close_at !== null && $now >= $vacancy->close_at)
            ? VacancyStatus::Closed
            : VacancyStatus::Published;
    }

    /** @param list<VacancyStatus> $allowed */
    private function requireFrom(VacancyStatus $from, array $allowed, VacancyStatus $to): VacancyStatus
    {
        if (! in_array($from, $allowed, true)) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }

        return $to;
    }
}
