<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Application\Models\ApplicationDocument;
use App\Domains\Application\Models\ApplicationScreeningAnswer;
use App\Domains\Application\Models\ApplicationStatusHistory;

/**
 * Candidate-facing application read model. Every field is a deliberate
 * allow-list — no recruiter-internal note, no other candidate's data, no
 * evaluation/offer/schedule content (those domains do not exist yet in this
 * milestone; their `include` keys are simply absent, never fabricated).
 */
final class ApplicationPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Application $application): array
    {
        return [
            'id' => (int) $application->getKey(),
            'application_code' => $application->application_code,
            'vacancy_id' => (int) $application->vacancy_id,
            'current_status' => $application->current_status?->value,
            'first_applied_at' => $application->first_applied_at?->toIso8601String(),
            'last_reopened_at' => $application->last_reopened_at?->toIso8601String(),
            'reopen_count' => $application->reopen_count,
            'withdrawn_at' => $application->withdrawn_at?->toIso8601String(),
        ];
    }

    /** @param list<string> $include @return array<string, mixed> */
    public static function detail(Application $application, array $include = []): array
    {
        $data = self::summary($application);

        if (in_array('history', $include, true)) {
            // candidate_visibility governs whether the candidate sees the EVENT
            // at all (DATA_DICTIONARY.md), not merely its note — an INTERNAL
            // event is filtered out entirely, never shown with a nulled note.
            $data['history'] = $application->statusHistories()->where('candidate_visibility', 'VISIBLE')
                ->orderBy('occurred_at')->orderBy('id')
                ->get()->map(self::historyEvent(...))->values()->all();
        }
        if (in_array('documents', $include, true)) {
            $data['documents'] = $application->documents()->whereNull('revoked_at')->orderBy('id')
                ->get()->map(self::documentShare(...))->values()->all();
        }
        if (in_array('screening_answers', $include, true)) {
            $data['screening_answers'] = $application->screeningAnswers()->orderBy('id')
                ->get()->map(self::screeningAnswer(...))->values()->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function historyEvent(ApplicationStatusHistory $event): array
    {
        return [
            'event_type' => $event->event_type?->value,
            'from_status' => $event->from_status?->value,
            'to_status' => $event->to_status?->value,
            'candidate_visible_note' => $event->candidate_visible_note,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private static function documentShare(ApplicationDocument $share): array
    {
        return [
            'id' => (int) $share->getKey(),
            'candidate_document_id' => (int) $share->candidate_document_id,
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
