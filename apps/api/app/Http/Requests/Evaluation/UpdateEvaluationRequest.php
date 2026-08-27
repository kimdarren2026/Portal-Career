<?php

declare(strict_types=1);

namespace App\Http\Requests\Evaluation;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `PATCH /evaluations/{evaluation}` transport-level shape only. Scalar
 * fields only in Foundation v1 — `items[]` mutation is out of scope (see the
 * frozen contract's PATCH-scope amendment note); this request deliberately
 * accepts no `items` field.
 */
final class UpdateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recruitment_stage_id' => ['sometimes', 'integer'],
            'recommendation' => ['sometimes', 'nullable', 'string', 'max:64'],
            'comments' => ['sometimes', 'nullable', 'string'],
            'total_score' => ['sometimes', 'nullable', 'numeric'],
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
