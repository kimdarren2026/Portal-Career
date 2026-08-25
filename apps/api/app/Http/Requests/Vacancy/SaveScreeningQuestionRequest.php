<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

use App\Domains\Vacancy\Enums\ScreeningQuestionType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/** POST and PATCH /vacancies/{vacancy}/screening-questions. There is no DELETE. */
final class SaveScreeningQuestionRequest extends VacancyFormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'question_text' => [$required, 'string'],
            'question_type' => [$required, 'string', Rule::in(ScreeningQuestionType::values())],
            'required' => ['sometimes', 'boolean'],
            'options_definition' => ['sometimes', 'nullable', 'array'],
            'sort_order' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (): void {
            $type = ScreeningQuestionType::tryFrom((string) $this->input('question_type'));
            if ($type === null) {
                return;
            }
            $options = $this->input('options_definition');
            $hasOptions = is_array($options) && $options !== [];

            if ($type->requiresOptions() && ! $hasOptions) {
                $this->fail('VALIDATION_FAILED', 'Data yang dikirim tidak valid.', [
                    'fields' => ['options_definition' => ['Wajib diisi untuk SINGLE_CHOICE.']],
                ]);
            }
            if (! $type->requiresOptions() && $hasOptions) {
                $this->fail('VALIDATION_FAILED', 'Data yang dikirim tidak valid.', [
                    'fields' => ['options_definition' => ['Hanya berlaku untuk SINGLE_CHOICE.']],
                ]);
            }
        });
    }
}
