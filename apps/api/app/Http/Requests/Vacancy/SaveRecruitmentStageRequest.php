<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

/**
 * POST and PATCH /vacancies/{vacancy}/stages(/{stage}). There is no DELETE —
 * `active = false` is the only deactivation path (mirrors screening
 * questions, `API_SIZE_REVIEW.md` Q-1).
 *
 * `sort_order` is accepted only on create. `POST .../stages/reorder` is the
 * sole post-creation operation permitted to change it — a whole-set, atomic,
 * lock-serialized write. Allowing `PATCH` to also carry `sort_order` would
 * reopen exactly the risk `API_SIZE_REVIEW.md` Q-4 designed the dedicated
 * reorder endpoint to close: individual `PATCH` calls can leave transient
 * duplicate positions or a partial-failure state, since `recruitment_stages`
 * carries no unique constraint on `(vacancy_id, sort_order)` to fall back on.
 * `PATCH` rejects a supplied `sort_order` outright (`422 VALIDATION_FAILED`)
 * rather than silently discarding it.
 */
final class SaveRecruitmentStageRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'stage_type' => [$required, 'string', 'max:64'],
            'sort_order' => $creating ? ['required', 'integer', 'min:0'] : ['prohibited'],
            'active' => [$required, 'boolean'],
            'candidate_visible_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
