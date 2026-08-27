<?php

declare(strict_types=1);

namespace App\Http\Requests\SelectionSchedule;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `POST /applications/{application}/schedules` transport-level shape only.
 * SS-1 (terminal), SS-3 (inactive stage), SS-5 (future time), and SS-8
 * (RA-2) are business rules enforced by `CreateSelectionSchedule` inside the
 * Action — this class cannot see locked application/vacancy/stage state.
 * `selection_type` stays open vocabulary (no `in:` list) — same design as
 * `recruitment_stages.stage_type`, never hard-coded from UI wording.
 */
final class CreateSelectionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recruitment_stage_id' => ['required', 'integer'],
            'selection_type' => ['required', 'string', 'max:64'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'method' => ['required', 'string', 'in:ONLINE,ON_SITE'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'pic_user_id' => ['sometimes', 'nullable', 'integer'],
            'instructions' => ['sometimes', 'nullable', 'string'],
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
