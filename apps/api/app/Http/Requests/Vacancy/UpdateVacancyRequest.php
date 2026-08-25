<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

use Illuminate\Contracts\Validation\Validator;

/**
 * PATCH /vacancies/{vacancy}.
 *
 * Scalar attributes only. `requirements[]` and `screening_questions[]` are
 * deliberately absent: the contract lists them on this request but never states
 * how an inline collection interacts with rows that already exist, and that
 * precedence is not invented here. Screening questions remain fully editable
 * through their own frozen routes.
 *
 * `vacancy_type` is also absent — the create contract fixes it and no contract
 * describes changing it after creation.
 */
final class UpdateVacancyRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        return $this->attributeRules(creating: false);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['requirements', 'screening_questions', 'vacancy_type', 'current_status'] as $unsupported) {
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
