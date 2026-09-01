<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

use App\Domains\Vacancy\Enums\ApplicationMethod;
use App\Domains\Vacancy\Enums\VacancyType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /hr/vacancies — create a campus vacancy (FR-HR-001, FR-HR-002).
 *
 * Campus authoring differs from company authoring on exactly three points:
 *  - `vacancy_type` is fixed to `CAMPUS_EMPLOYMENT` (never COMPANY_EMPLOYMENT).
 *  - `organizational_unit_id` is REQUIRED and must be an active unit
 *    (ownership XOR — INV-018; FR-HR-002 lists unit/fakultas/bagian).
 *  - `application_method` must be `IN_PORTAL`; External ATS is refused with
 *    the frozen `VACANCY_EXTERNAL_ATS_NOT_ALLOWED_FOR_CAMPUS` (INV-005,
 *    FR-HR-003) — the API never offers it.
 *
 * Every other field, requirement and screening-question rule is the shared
 * `VacancyFormRequest` set, unchanged.
 */
final class CreateCampusVacancyRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        return array_merge(
            [
                'vacancy_type' => ['sometimes', 'string', Rule::in([VacancyType::CampusEmployment->value])],
                'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')->where('active', true)],
            ],
            $this->attributeRules(creating: true),
            $this->requirementRules(),
            $this->screeningQuestionRules(),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // FR-HR-003 / INV-005: campus vacancies are IN_PORTAL only.
            if ($this->input('application_method') === ApplicationMethod::ExternalAts->value) {
                $this->fail('VACANCY_EXTERNAL_ATS_NOT_ALLOWED_FOR_CAMPUS', 'Lowongan Karier di Kampus harus menggunakan lamaran melalui portal.');
            }

            $this->assertCampusContractRules();
        });
    }

    /**
     * The shared `assertContractSpecificRules()` rejects any
     * `organizational_unit_id` (company XOR). Campus requires it, so this
     * request runs the shared sub-checks individually and skips that one.
     */
    private function assertCampusContractRules(): void
    {
        $this->assertExternalAtsRules();
        $this->assertDateOrder();
        $this->assertRequirementTypedValues();
        $this->assertScreeningQuestionOptions();

        if ($this->has('target_audience')
            && ! in_array($this->input('target_audience'), \App\Domains\Vacancy\Enums\TargetAudience::values(), true)) {
            $this->fail('VACANCY_TARGET_AUDIENCE_INVALID', 'Target kandidat tidak valid.');
        }
    }
}
