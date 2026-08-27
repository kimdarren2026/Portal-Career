<?php

declare(strict_types=1);

namespace App\Http\Requests\SelectionSchedule;

use App\Domains\Recruitment\SelectionSchedule\Queries\ListSelectionSchedules;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ListSelectionSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => ['nullable', 'integer'],
            'recruitment_stage_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'starts_from' => ['nullable', 'date'],
            'starts_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [...ListSelectionSchedules::FILTERS, 'sort', 'direction', 'page'];
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
