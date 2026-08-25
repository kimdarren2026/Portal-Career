<?php

declare(strict_types=1);

namespace App\Http\Requests\Vacancy;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Transport shape for the moderation endpoints. INV-029's mandatory reason is
 * enforced in the Action, which owns the rule for every caller and answers
 * `REVIEW_REASON_REQUIRED`; this class only bounds the shape.
 */
final class ModerateVacancyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason_category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recruiter_visible_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, ['reason_category', 'recruiter_visible_note', 'internal_note', 'note'], true)) {
                    $validator->errors()->add($field, 'Field tidak didukung pada operasi ini.');
                }
            }
        });
    }

    /** @return array<string, mixed> */
    public function moderationDetails(): array
    {
        return [
            'reason_category' => $this->input('reason_category'),
            'recruiter_visible_note' => $this->input('recruiter_visible_note'),
            'internal_note' => $this->input('internal_note'),
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
