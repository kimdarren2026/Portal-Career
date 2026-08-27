<?php

declare(strict_types=1);

namespace App\Http\Requests\Outcome;

use App\Domains\Recruitment\Outcome\Queries\ListRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Support\InternalApplicationOutcome;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

final class ListRecruitmentOutcomesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => ['nullable', 'integer'],
            'outcome' => ['nullable', 'string', Rule::in(InternalApplicationOutcome::ALLOWED)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [...ListRecruitmentOutcomes::FILTERS, 'page'];
            foreach (array_keys($this->query()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, 'Filter tidak didukung.');
                }
            }
        });
    }

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
}
