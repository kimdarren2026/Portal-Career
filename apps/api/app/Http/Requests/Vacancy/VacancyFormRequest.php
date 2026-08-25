<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

use App\Domains\Vacancy\Enums\ApplicationMethod;
use App\Domains\Vacancy\Enums\RequirementType;
use App\Domains\Vacancy\Enums\ScreeningQuestionType;
use App\Domains\Vacancy\Enums\TargetAudience;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Vacancy transport base.
 *
 * Shape failures answer `VALIDATION_FAILED`. The five conditions the contract
 * names individually answer their own frozen code instead, because a client
 * that sends an external vacancy without a URL needs to be told which rule it
 * broke, not merely that something failed.
 */
abstract class VacancyFormRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ContractResponse::error(
            $this,
            'VALIDATION_FAILED',
            422,
            'Data yang dikirim tidak valid.',
            ['fields' => $validator->errors()->toArray()],
        ));
    }

    /** @return array<string, list<mixed>> */
    protected function attributeRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:3', 'max:255'],
            'description' => [$required, 'string'],
            'employment_type' => [$required, 'string', 'max:64'],
            'openings_count' => [$required, 'integer', 'min:1'],
            'target_audience' => [$required, 'string'],
            'application_method' => [$required, 'string', Rule::in([ApplicationMethod::InPortal->value, ApplicationMethod::ExternalAts->value])],
            'responsibilities' => ['sometimes', 'nullable', 'string'],
            'workplace_mode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'province_geographic_area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('geographic_areas', 'id')->where('active', true)],
            'city_geographic_area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('geographic_areas', 'id')->where('active', true)],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'minimum_education' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience_requirement' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Salary remains an open policy item: nullable, never mandatory,
            // and no presentation flag is introduced.
            'salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'external_ats_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'open_at' => ['sometimes', 'nullable', 'date'],
            'close_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return array<string, list<mixed>> */
    protected function requirementRules(): array
    {
        return [
            'requirements' => ['sometimes', 'array'],
            'requirements.*.requirement_type' => ['required', 'string', Rule::in(RequirementType::values())],
            'requirements.*.education_level' => ['sometimes', 'nullable', 'string', 'max:64'],
            'requirements.*.study_program_id' => ['sometimes', 'nullable', 'integer', Rule::exists('study_programs', 'id')->where('active', true)],
            'requirements.*.skill_id' => ['sometimes', 'nullable', 'integer', Rule::exists('skills', 'id')->where('active', true)],
            'requirements.*.minimum_years_experience' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'requirements.*.value_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requirements.*.note' => ['sometimes', 'nullable', 'string'],
            'requirements.*.required' => ['sometimes', 'boolean'],
            'requirements.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, list<mixed>> */
    protected function screeningQuestionRules(): array
    {
        return [
            'screening_questions' => ['sometimes', 'array'],
            'screening_questions.*.question_text' => ['required', 'string'],
            'screening_questions.*.question_type' => ['required', 'string', Rule::in(ScreeningQuestionType::values())],
            'screening_questions.*.required' => ['sometimes', 'boolean'],
            'screening_questions.*.options_definition' => ['sometimes', 'nullable', 'array'],
            'screening_questions.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'screening_questions.*.active' => ['sometimes', 'boolean'],
        ];
    }

    /** Conditions the contract gives their own error code. */
    protected function assertContractSpecificRules(): void
    {
        if ($this->has('organizational_unit_id')) {
            // Ownership XOR (INV-018): a company vacancy never carries one.
            $this->fail('VACANCY_OWNERSHIP_INVALID', 'Lowongan perusahaan tidak boleh memiliki unit organisasi.');
        }

        if ($this->has('target_audience') && ! in_array($this->input('target_audience'), TargetAudience::values(), true)) {
            $this->fail('VACANCY_TARGET_AUDIENCE_INVALID', 'Target kandidat tidak valid.');
        }

        $this->assertExternalAtsRules();
        $this->assertDateOrder();
        $this->assertRequirementTypedValues();
        $this->assertScreeningQuestionOptions();
    }

    protected function assertExternalAtsRules(): void
    {
        $url = $this->input('external_ats_url');
        $method = $this->input('application_method');

        if ($method === ApplicationMethod::ExternalAts->value && (! is_string($url) || trim($url) === '')) {
            $this->fail('VACANCY_EXTERNAL_ATS_URL_REQUIRED', 'URL ATS eksternal wajib diisi.');
        }

        // FSD §9.1.5: https only. No normalization rule is frozen, so the value
        // is accepted as sent once the scheme is admitted.
        if (is_string($url) && trim($url) !== '' && ! str_starts_with(mb_strtolower(trim($url)), 'https://')) {
            $this->fail('VACANCY_EXTERNAL_ATS_URL_INVALID', 'URL ATS eksternal harus menggunakan HTTPS.');
        }
    }

    protected function assertDateOrder(): void
    {
        $openAt = $this->input('open_at');
        $closeAt = $this->input('close_at');

        if (! empty($openAt) && ! empty($closeAt) && strtotime((string) $closeAt) <= strtotime((string) $openAt)) {
            $this->fail('VACANCY_CLOSE_BEFORE_OPEN', 'Tanggal tutup harus setelah tanggal buka.');
        }
    }

    /** chk_vacancy_requirements_typed_value: exactly one typed value per type. */
    protected function assertRequirementTypedValues(): void
    {
        foreach ((array) $this->input('requirements', []) as $index => $requirement) {
            if (! is_array($requirement) || ! isset($requirement['requirement_type'])) {
                continue;
            }
            $type = RequirementType::tryFrom((string) $requirement['requirement_type']);
            if ($type === null) {
                continue;
            }
            $column = $type->valueColumn();
            if (($requirement[$column] ?? null) === null || $requirement[$column] === '') {
                $this->fail('VALIDATION_FAILED', 'Data yang dikirim tidak valid.', [
                    'fields' => ["requirements.$index.$column" => ['Wajib diisi untuk requirement_type '.$type->value.'.']],
                ]);
            }
        }
    }

    /** options_definition is required for SINGLE_CHOICE and rejected for other types. */
    protected function assertScreeningQuestionOptions(): void
    {
        foreach ((array) $this->input('screening_questions', []) as $index => $question) {
            if (! is_array($question) || ! isset($question['question_type'])) {
                continue;
            }
            $type = ScreeningQuestionType::tryFrom((string) $question['question_type']);
            if ($type === null) {
                continue;
            }
            $options = $question['options_definition'] ?? null;
            $hasOptions = is_array($options) && $options !== [];

            if ($type->requiresOptions() && ! $hasOptions) {
                $this->fail('VALIDATION_FAILED', 'Data yang dikirim tidak valid.', [
                    'fields' => ["screening_questions.$index.options_definition" => ['Wajib diisi untuk SINGLE_CHOICE.']],
                ]);
            }
            if (! $type->requiresOptions() && $hasOptions) {
                $this->fail('VALIDATION_FAILED', 'Data yang dikirim tidak valid.', [
                    'fields' => ["screening_questions.$index.options_definition" => ['Hanya berlaku untuk SINGLE_CHOICE.']],
                ]);
            }
        }
    }

    /** @param array<string, mixed> $details */
    protected function fail(string $code, string $message, array $details = []): never
    {
        throw new HttpResponseException(ContractResponse::error($this, $code, 422, $message, $details));
    }
}
