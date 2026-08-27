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
 * `PATCH /recruitment-outcomes/{outcome}` — correction only. The source
 * reference itself is never editable: `source_type`, `application_id`, and
 * `external_apply_event_id` are not accepted fields here at all, not merely
 * ignored.
 */
final class UpdateRecruitmentOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['sometimes', 'string', Rule::in(InternalApplicationOutcome::ALLOWED)],
            'reported_by_source' => ['sometimes', 'string', Rule::in(['CANDIDATE', 'COMPANY', 'CAMPUS_STAFF', 'INTEGRATION'])],
            'notes' => ['sometimes', 'nullable', 'string'],
            'source_type' => ['prohibited'],
            'application_id' => ['prohibited'],
            'external_apply_event_id' => ['prohibited'],
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
