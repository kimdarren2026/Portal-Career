<?php

declare(strict_types=1);

namespace App\Http\Requests\Application;

use App\Domains\Application\Queries\ListCandidateApplications;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ListApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vacancy_id' => ['nullable', 'integer'],
            'current_status' => ['nullable', 'string'],
            'applied_from' => ['nullable', 'date'],
            'applied_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [...ListCandidateApplications::FILTERS, 'sort', 'direction', 'page'];
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
