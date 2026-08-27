<?php

declare(strict_types=1);

namespace App\Http\Requests\Offering;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /applications/{application}/offers` transport-level shape only.
 * `document_reference` is deliberately absent — no upload mechanism is
 * wired in Foundation v1 (see the create contract's amendment note).
 */
final class CreateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response_deadline' => ['sometimes', 'nullable', 'date', 'after:now'],
            'note' => ['sometimes', 'nullable', 'string'],
            'send_now' => ['sometimes', 'boolean'],
        ];
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
