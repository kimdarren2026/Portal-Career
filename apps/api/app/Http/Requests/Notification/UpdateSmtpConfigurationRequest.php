<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `PUT /admin/smtp-configuration` (API_CONTRACT.md, FR-NOTIF-005). Exactly the
 * frozen field set and constraints — no field is added or inferred. The DB
 * check constraints (`chk_smtp_configurations_*`) are the final authority;
 * these bounds mirror them so the user gets `422`, not a `500`.
 *
 * `password` is `sometimes` + `nullable`: omitted preserves the stored
 * secret, a string replaces it, an explicit `null` clears it. The Action
 * distinguishes the three via `has('password')`.
 */
final class UpdateSmtpConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption_mode' => ['required', 'string', 'in:NONE,STARTTLS,TLS'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'reply_to_address' => ['nullable', 'email:rfc', 'max:255'],
            'timeout_seconds' => ['nullable', 'integer', 'between:1,600'],
            'max_attempts' => ['required', 'integer', 'between:1,20'],
            'retry_backoff_seconds' => ['required', 'integer', 'between:1,86400'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, mixed> The non-secret fields, ready for the Action. */
    public function configData(): array
    {
        return $this->safe()->except('password');
    }

    public function secretProvided(): bool
    {
        return $this->has('password');
    }

    public function plainSecret(): ?string
    {
        $value = $this->input('password');

        return $value === null ? null : (string) $value;
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
