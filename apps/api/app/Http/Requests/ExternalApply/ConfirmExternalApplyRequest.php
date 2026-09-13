<?php

declare(strict_types=1);

namespace App\Http\Requests\ExternalApply;

use App\Domains\ExternalApply\Models\ExternalApplyEvent;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /external-apply-events/{event}/confirm` transport shape only.
 *
 * The `confirmation_status` VALUE vocabulary is not frozen anywhere (the
 * column is an unconstrained `string(64)` with no CHECK, and the contract
 * says only "Enum membership" without listing it). This class therefore
 * enforces shape — required, non-empty, ≤ 64 chars, and never the reserved
 * start-state `PENDING` — and invents no business vocabulary. Authorization
 * (legitimate confirmation source) and the already-confirmed guard live in
 * `ConfirmExternalApply`.
 */
final class ConfirmExternalApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'confirmation_status' => ['required', 'string', 'max:64', 'not_in:'.ExternalApplyEvent::STATUS_PENDING],
            'confirmation_source' => ['required', 'string', 'max:64'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
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
