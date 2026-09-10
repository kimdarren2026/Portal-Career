<?php

declare(strict_types=1);

namespace App\Http\Requests\Selection;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /stages/{stage}/selector-assignments` transport shape only.
 * `selector_user_id` is the only field. Every business rule — the target's
 * active SELECTOR role, campus-only stage scope, duplicate-active rejection —
 * is enforced inside `AssignSelectorToStage`.
 */
final class AssignSelectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'selector_user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function selectorUserId(): int
    {
        return (int) $this->validated('selector_user_id');
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
