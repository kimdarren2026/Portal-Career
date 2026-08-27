<?php

declare(strict_types=1);

namespace App\Http\Requests\SelectionSchedule;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `PATCH /schedules/{schedule}` transport-level shape only.
 * `recruitment_stage_id` is deliberately absent — the frozen contract never
 * allows reschedule to change the schedule's stage.
 */
final class RescheduleSelectionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'method' => ['sometimes', 'string', 'in:ONLINE,ON_SITE'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'pic_user_id' => ['sometimes', 'nullable', 'integer'],
            'instructions' => ['sometimes', 'nullable', 'string'],
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
