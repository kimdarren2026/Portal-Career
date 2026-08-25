<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\VacancyModerationAction;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Exceptions\VacancyCloseBeforeOpen;
use App\Domains\Vacancy\Exceptions\VacancyCompanyNotVerified;
use App\Domains\Vacancy\Exceptions\VacancyDatesRequired;
use App\Domains\Vacancy\Exceptions\VacancyExternalUrlInvalid;
use App\Domains\Vacancy\Exceptions\VacancyExternalUrlRequired;
use App\Domains\Vacancy\Exceptions\VacancyInvalidTransition;
use App\Domains\Vacancy\Exceptions\VacancyModerationNotApplicable;
use App\Domains\Vacancy\Exceptions\VacancyProfileIncomplete;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyLifecycleNotifier;
use App\Domains\Vacancy\Support\VacancySubmissionCompleteness;
use Illuminate\Support\Facades\DB;

/**
 * POST /vacancies/{vacancy}/submit-review (FR-VAC-004, FSD §8.3).
 *
 * Submit is an ACTION: `PENDING_REVIEW` is the stored status and
 * `SUBMITTED`/`DIAJUKAN` never exist (INV-004). The same vacancy row is
 * revised; nothing is replaced.
 *
 * One transaction: lock → source status → company VERIFIED recheck →
 * B-5 completeness against persisted state → moderation review → transition →
 * audit → outbox. A failure at any step rolls all of it back, so an incomplete
 * submit leaves no review row, no audit entry and no queued notification.
 */
final class SubmitVacancyForReview
{
    public function __construct(
        private readonly VacancySubmissionCompleteness $completeness,
        private readonly AuditWriter $audit,
        private readonly VacancyLifecycleNotifier $notifier,
    ) {}

    public function execute(User $actor, Vacancy $vacancy): Vacancy
    {
        return DB::transaction(function () use ($actor, $vacancy): Vacancy {
            /** @var Vacancy $locked */
            $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

            // Campus vacancies are never moderated (INV-018).
            if (! $locked->isCompanyOwned()) {
                throw new VacancyModerationNotApplicable('VACANCY_MODERATION_NOT_APPLICABLE');
            }

            $from = $locked->current_status;
            if ($from !== VacancyStatus::Draft && $from !== VacancyStatus::RevisionRequired) {
                throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
            }

            // INV-002: the gate is re-read at submit, not trusted from creation.
            /** @var Company $company */
            $company = Company::query()->whereKey($locked->company_id)->lockForUpdate()->firstOrFail();
            if ($company->verification_status !== CompanyStatus::Verified) {
                throw new VacancyCompanyNotVerified('VACANCY_COMPANY_NOT_VERIFIED');
            }

            $this->assertComplete($locked);

            $locked->moderationReviews()->forceCreate([
                'reviewer_user_id' => $actor->getKey(),
                'action' => VacancyModerationAction::Submit->value,
                'from_status' => $from->value,
                'to_status' => VacancyStatus::PendingReview->value,
                'reviewed_at' => now(),
            ]);

            $locked->current_status = VacancyStatus::PendingReview;
            $locked->updated_at = now();
            $locked->save();

            $this->audit->record('vacancy_submitted', $actor, 'vacancy', (int) $locked->getKey(), [
                'from_status' => $from->value,
                'to_status' => VacancyStatus::PendingReview->value,
            ]);
            $this->notifier->queue($locked, VacancyModerationAction::Submit);

            return $locked->refresh();
        });
    }

    /** B-5. Specific frozen codes win over the generic completeness code. */
    private function assertComplete(Vacancy $vacancy): void
    {
        if ($this->completeness->datesMissing($vacancy)) {
            throw new VacancyDatesRequired('VACANCY_DATES_REQUIRED');
        }
        if ($this->completeness->closeBeforeOpen($vacancy)) {
            throw new VacancyCloseBeforeOpen('VACANCY_CLOSE_BEFORE_OPEN');
        }
        if ($this->completeness->externalUrlMissing($vacancy)) {
            throw new VacancyExternalUrlRequired('VACANCY_EXTERNAL_ATS_URL_REQUIRED');
        }
        if ($this->completeness->externalUrlInvalid($vacancy)) {
            throw new VacancyExternalUrlInvalid('VACANCY_EXTERNAL_ATS_URL_INVALID');
        }

        $missing = $this->completeness->missing($vacancy);
        if ($missing !== []) {
            throw new VacancyProfileIncomplete($missing);
        }
    }
}
