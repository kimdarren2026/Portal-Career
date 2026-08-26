<?php

declare(strict_types=1);

namespace App\Http\Requests\Application;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /applications/{application}/transition` transport-level shape only.
 * `to_status` is validated against the full frozen enum here — RA-1's
 * Foundation v1 subset (which edges are actually legal from which source) is
 * a business rule enforced by `ApplicationTransitionGraph` inside the
 * Action, not a request-shape rule.
 */
final class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', 'in:APPLIED,UNDER_REVIEW,SHORTLISTED,ASSESSMENT,INTERVIEW,OFFERED,HIRED,REJECTED,WITHDRAWN,NO_SHOW'],
            'candidate_visibility' => ['required', 'string', 'in:VISIBLE,INTERNAL'],
            'candidate_visible_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
