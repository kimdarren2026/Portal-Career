<?php

declare(strict_types=1);

namespace App\Http\Requests\Application;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /applications/{application}/move-stage` transport-level shape only.
 * MS-3 (same-stage rejection) and MS-4 (no adjacency rule) are business
 * rules enforced by `MoveApplicationStage` inside the Action, not a
 * request-shape rule — this class cannot see the application's current
 * `current_stage_id`.
 */
final class MoveApplicationStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_stage_id' => ['required', 'integer'],
            'candidate_visibility' => ['required', 'string', 'in:VISIBLE,INTERNAL'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
