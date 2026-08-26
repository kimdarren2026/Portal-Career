<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

/**
 * `POST /vacancies/{vacancy}/stages/reorder` — a whole-set atomic operation
 * (`API_SIZE_REVIEW.md` Q-4). The client submits the complete new ordering as
 * one ordered list; position in the array is the new `sort_order`.
 */
final class ReorderRecruitmentStagesRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        return [
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['required', 'integer'],
        ];
    }
}
