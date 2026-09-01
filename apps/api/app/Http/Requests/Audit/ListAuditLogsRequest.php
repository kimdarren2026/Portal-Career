<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Domains\Audit\Queries\ListAuditLogs;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Allow-listed filters for `GET /audit-logs` (API_CONTRACT.md Part XI):
 * `actor_user_id`, `action`, `object_type`, `object_id`, `correlation_id`,
 * `created_from`, `created_to`, plus `page` / `per_page`. Any other query
 * parameter is rejected `422 VALIDATION_FAILED`, matching the sibling
 * `GET /notifications` contract. Sort is fixed (`created_at` desc) and takes
 * no parameter.
 */
final class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'actor_user_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:128'],
            'object_type' => ['nullable', 'string', 'max:64'],
            'object_id' => ['nullable', 'integer', 'min:1'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [...ListAuditLogs::FILTERS, 'page', 'per_page'];
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
