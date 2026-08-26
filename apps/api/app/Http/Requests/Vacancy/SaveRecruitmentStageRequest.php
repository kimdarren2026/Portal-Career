<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

/**
 * POST and PATCH /vacancies/{vacancy}/stages(/{stage}). There is no DELETE —
 * `active = false` is the only deactivation path (mirrors screening
 * questions, `API_SIZE_REVIEW.md` Q-1).
 */
final class SaveRecruitmentStageRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'stage_type' => [$required, 'string', 'max:64'],
            'sort_order' => [$required, 'integer', 'min:0'],
            'active' => [$required, 'boolean'],
            'candidate_visible_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
