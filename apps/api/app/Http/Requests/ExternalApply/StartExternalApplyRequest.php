<?php

declare(strict_types=1);

namespace App\Http\Requests\ExternalApply;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /vacancies/{vacancy}/external-apply/start` transport shape only.
 * Every business rule (vacancy state, method, eligibility, consent version)
 * is enforced inside `StartExternalApply`. The destination URL is NEVER read
 * from the request — any `destination_url` / `external_ats_url` a client
 * sends is ignored; the server uses the stored vacancy's URL.
 */
final class StartExternalApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'consent' => ['required', 'array'],
            'consent.consent_version' => ['required', 'string'],
            'consent.consent_text_hash_reference' => ['sometimes', 'string'],
            'consent.accepted' => ['required', 'boolean'],
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
