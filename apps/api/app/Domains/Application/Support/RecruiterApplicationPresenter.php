<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Application\Models\ApplicationDocument;
use App\Domains\Application\Models\ApplicationScreeningAnswer;
use App\Domains\Application\Models\ApplicationStatusHistory;
use App\Domains\Candidate\Models\CandidateProfile;

/**
 * Recruiter/company/Super Admin read model for Recruiter Applicant
 * Management Foundation v1 — a deliberate allow-list, separate from the
 * candidate-facing `ApplicationPresenter` so neither can regress the other.
 *
 * Candidate profile fields are limited to `name` and `headline` — the
 * minimal identifying subset FR-CAN-003 authorizes as recruitment content.
 * No account email, phone, or other private/contact field is exposed;
 * broader exposure was not confirmed by any authoritative source and is not
 * invented here. Document entries are metadata only (RA-3): no
 * `snapshot_storage_reference`, no `snapshot_checksum`, no download URL.
 * History is the full authorized set — `reason` included, never filtered to
 * `candidate_visibility = VISIBLE` (that filter belongs only to the
 * candidate's own read).
 */
final class RecruiterApplicationPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Application $application): array
    {
        return [
            'id' => (int) $application->getKey(),
            'application_code' => $application->application_code,
            'vacancy_id' => (int) $application->vacancy_id,
            'current_status' => $application->current_status?->value,
            'current_stage_id' => $application->current_stage_id,
            'first_applied_at' => $application->first_applied_at?->toIso8601String(),
            'updated_at' => $application->updated_at?->toIso8601String(),
            'reopen_count' => $application->reopen_count,
            'withdrawn_at' => $application->withdrawn_at?->toIso8601String(),
            'candidate' => self::candidateSummary($application->candidateProfile),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Application $application): array
    {
        $data = self::summary($application);

        $data['current_version'] = $application->statusHistories()->count();

        $data['history'] = $application->statusHistories()
            ->orderBy('occurred_at')->orderBy('id')
            ->get()->map(self::historyEvent(...))->values()->all();

        $data['documents'] = $application->documents()->whereNull('revoked_at')->orderBy('id')
            ->get()->map(self::documentMetadata(...))->values()->all();

        $data['screening_answers'] = $application->screeningAnswers()->orderBy('id')
            ->get()->map(self::screeningAnswer(...))->values()->all();

        return $data;
    }

    /** @return array<string, mixed> */
    private static function candidateSummary(?CandidateProfile $profile): array
    {
        if ($profile === null) {
            return [];
        }

        return [
            'candidate_profile_id' => (int) $profile->getKey(),
            'name' => $profile->user?->name,
            'headline' => $profile->headline,
        ];
    }

    /** @return array<string, mixed> */
    private static function historyEvent(ApplicationStatusHistory $event): array
    {
        return [
            'event_type' => $event->event_type?->value,
            'from_status' => $event->from_status?->value,
            'to_status' => $event->to_status?->value,
            'actor_user_id' => $event->actor_user_id,
            'reason' => $event->reason,
            'candidate_visibility' => $event->candidate_visibility,
            'candidate_visible_note' => $event->candidate_visible_note,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private static function documentMetadata(ApplicationDocument $share): array
    {
        return [
            'id' => (int) $share->getKey(),
            'snapshot_name' => $share->snapshot_name,
            'shared_at' => $share->shared_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private static function screeningAnswer(ApplicationScreeningAnswer $answer): array
    {
        return [
            'screening_question_id' => (int) $answer->screening_question_id,
            'answer_text' => $answer->answer_text,
            'answer_boolean' => $answer->answer_boolean,
            'answer_number' => $answer->answer_number,
            'answer_option' => $answer->answer_option,
        ];
    }
}
