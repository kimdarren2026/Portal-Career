<?php

declare(strict_types=1);

namespace App\Domains\ExternalApply\Actions;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\ExternalApply\Exceptions\ExternalApplyConfirmationForbidden;
use App\Domains\ExternalApply\Exceptions\ExternalApplyEventAlreadyConfirmed;
use App\Domains\ExternalApply\Exceptions\ExternalApplyEventNotFound;
use App\Domains\ExternalApply\Models\ExternalApplyEvent;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/**
 * `POST /external-apply-events/{event}/confirm` (FR-EXT-003).
 *
 * Records that an external application actually happened or concluded. A
 * **legitimate confirmation source only**: the candidate who started the
 * event, or an authorized active member of the owning company. No two-way
 * ATS integration is specified — there is no synchronization, polling,
 * callback, or webhook path here. `confirmation_status` is NOT an application
 * status and is never mapped onto one (INV-012); confirmation still creates
 * no `applications` row.
 */
final class ConfirmExternalApply
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $data */
    public function execute(User $actor, int $eventId, array $data): ExternalApplyEvent
    {
        return DB::transaction(function () use ($actor, $eventId, $data): ExternalApplyEvent {
            /** @var ExternalApplyEvent|null $event */
            $event = ExternalApplyEvent::query()->whereKey($eventId)->lockForUpdate()->first();
            if ($event === null) {
                throw new ExternalApplyEventNotFound();
            }

            if (! $this->isLegitimateSource($actor, $event)) {
                throw new ExternalApplyConfirmationForbidden();
            }

            if ($event->confirmed_at !== null) {
                throw new ExternalApplyEventAlreadyConfirmed();
            }

            $now = now();
            $event->forceFill([
                'confirmation_status' => (string) $data['confirmation_status'],
                'confirmation_source' => (string) $data['confirmation_source'],
                'confirmed_at' => $now,
                'confirmed_by' => $actor->getKey(),
            ])->save();

            $this->audit->record('external_apply_confirmed', $actor, 'external_apply_event', (int) $event->getKey(), [
                'vacancy_id' => (int) $event->vacancy_id,
                'confirmation_status' => (string) $data['confirmation_status'],
            ]);

            return $event->refresh();
        });
    }

    private function isLegitimateSource(User $actor, ExternalApplyEvent $event): bool
    {
        $ownProfileId = CandidateProfile::query()->where('user_id', $actor->getKey())->value('id');
        if ($ownProfileId !== null && (int) $ownProfileId === (int) $event->candidate_profile_id) {
            return true;
        }

        $companyId = $event->vacancy()->value('company_id');
        if ($companyId === null) {
            return false;
        }

        return DB::table('company_members')
            ->where('company_id', $companyId)
            ->where('user_id', $actor->getKey())
            ->where('status', 'ACTIVE')
            ->whereNull('revoked_at')
            ->exists();
    }
}
