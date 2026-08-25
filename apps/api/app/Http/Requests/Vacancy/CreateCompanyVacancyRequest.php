<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

use App\Domains\Vacancy\Enums\VacancyType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /companies/{company}/vacancies.
 *
 * A company authors `COMPANY_EMPLOYMENT` or `INTERNSHIP` only; campus vacancies
 * are a separate flow (INV-018). DRAFT is allowed to be incomplete — the submit
 * completeness set is an unresolved item and is not enforced here.
 */
final class CreateCompanyVacancyRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        return array_merge(
            ['vacancy_type' => ['required', 'string', Rule::in(VacancyType::companyAuthorable())]],
            $this->attributeRules(creating: true),
            $this->requirementRules(),
            $this->screeningQuestionRules(),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn () => $this->assertContractSpecificRules());
    }
}
