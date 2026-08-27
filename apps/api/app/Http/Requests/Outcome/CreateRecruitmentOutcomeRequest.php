<?php

declare(strict_types=1);

namespace App\Http\Requests\Outcome;

use App\Domains\Recruitment\Outcome\Support\InternalApplicationOutcome;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `POST /recruitment-outcomes` transport-level shape. `source_type` must be
 * `INTERNAL_APPLICATION` in Foundation v1 — no `EXTERNAL_APPLY` runtime
 * exists, and `external_apply_event_id` is never accepted here.
 * `reported_by_source` is client-supplied, validated against the exact
 * schema `CHECK` vocabulary (no actor-derived mapping is invented).
 */
final class CreateRecruitmentOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', Rule::in(['INTERNAL_APPLICATION'])],
            'application_id' => ['required', 'integer'],
            'external_apply_event_id' => ['prohibited'],
            'outcome' => ['required', 'string', Rule::in(InternalApplicationOutcome::ALLOWED)],
            'reported_by_source' => ['required', 'string', Rule::in(['CANDIDATE', 'COMPANY', 'CAMPUS_STAFF', 'INTEGRATION'])],
            'notes' => ['sometimes', 'nullable', 'string'],
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
