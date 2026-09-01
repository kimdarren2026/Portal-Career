<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Domains\Notification\Queries\ListNotifications;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Allow-listed filters and sort fields for `GET /notifications`
 * (API_CONTRACT.md Part IX): `read`, `type`, `created_from`, `created_to`,
 * plus `page`. Any other query parameter is rejected `422 VALIDATION_FAILED`.
 */
final class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Query strings are always strings; accept the boolean spellings a
            // browser sends and let `ListNotifications` coerce with filter_var.
            'read' => ['nullable', 'string', 'in:true,false,1,0'],
            'type' => ['nullable', 'string', 'max:64'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [...ListNotifications::FILTERS, 'page'];
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
