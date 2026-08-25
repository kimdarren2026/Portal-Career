<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

use Illuminate\Contracts\Validation\Validator;

/**
 * PATCH /vacancies/{vacancy}.
 *
 * Editable attributes plus optional `requirements[]` (PO decision VA-1):
 * omitted preserves the stored collection, present replaces it completely,
 * `[]` clears it. Entries are validated exactly as on create.
 *
 * `screening_questions[]` is NOT accepted here (PO decision VA-2). Screening
 * questions are mutated only through their own frozen routes, and a
 * `screening_questions` key is rejected by the ordinary unsupported-field
 * convention — no new error code.
 *
 * `vacancy_type` is also absent — the create contract fixes it and no contract
 * describes changing it after creation.
 */
final class UpdateVacancyRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        return array_merge($this->attributeRules(creating: false), $this->requirementRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['screening_questions', 'vacancy_type', 'current_status'] as $unsupported) {
                if ($this->has($unsupported)) {
                    $validator->errors()->add($unsupported, 'Field tidak didukung pada operasi ini.');
                }
            }

            if ($validator->errors()->isEmpty()) {
                $this->assertContractSpecificRules();
            }
        });
    }
}
