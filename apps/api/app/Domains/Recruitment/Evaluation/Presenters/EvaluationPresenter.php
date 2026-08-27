<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Presenters;

use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use App\Domains\Recruitment\Evaluation\Models\EvaluationItem;

/**
 * The single operational (recruiter/admin/Super Admin) allow-list.
 * Evaluations are never candidate-visible (FR-HR-007) — no candidate
 * presenter exists for this domain at all.
 */
final class EvaluationPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Evaluation $evaluation): array
    {
        return [
            'id' => (int) $evaluation->getKey(),
            'application_id' => (int) $evaluation->application_id,
            'recruitment_stage_id' => (int) $evaluation->recruitment_stage_id,
            'evaluator_user_id' => (int) $evaluation->evaluator_user_id,
            'recommendation' => $evaluation->recommendation,
            'comments' => $evaluation->comments,
            'total_score' => $evaluation->total_score,
            'submitted_at' => $evaluation->submitted_at?->toIso8601String(),
            'created_at' => $evaluation->created_at?->toIso8601String(),
            'updated_at' => $evaluation->updated_at?->toIso8601String(),
            'items' => $evaluation->items->map(self::item(...))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function item(EvaluationItem $item): array
    {
        return [
            'id' => (int) $item->getKey(),
            'criterion' => $item->criterion,
            'weight' => $item->weight,
            'score' => $item->score,
            'comment' => $item->comment,
            'sort_order' => $item->sort_order,
        ];
    }
}
