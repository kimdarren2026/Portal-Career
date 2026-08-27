<?php

declare(strict_types=1);

namespace App\Http\Requests\Evaluation;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /applications/{application}/evaluations` transport-level shape
 * only. EV-1 (terminal), EV-2 (stage alignment/active), and RC-1 (RA-2) are
 * business rules enforced by `CreateEvaluation` inside the Action — this
 * class cannot see locked application/vacancy/stage state. `recommendation`
 * stays open vocabulary (no `in:` list), same design as `selection_type`.
 */
final class CreateEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recruitment_stage_id' => ['required', 'integer'],
            'recommendation' => ['sometimes', 'nullable', 'string', 'max:64'],
            'comments' => ['sometimes', 'nullable', 'string'],
            'total_score' => ['sometimes', 'nullable', 'numeric'],
            'items' => ['sometimes', 'array'],
            'items.*.criterion' => ['required_with:items', 'string', 'max:255'],
            'items.*.weight' => ['sometimes', 'nullable', 'numeric'],
            'items.*.score' => ['sometimes', 'nullable', 'numeric'],
            'items.*.comment' => ['sometimes', 'nullable', 'string'],
            'items.*.sort_order' => ['required_with:items', 'integer'],
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
