<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Domains\VacancyReport\Support\VacancyReportVocabulary;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `POST /lowongan/{vacancy}/laporkan` (PGC-V1 / PD-C). Public — no auth. The
 * vacancy is resolved from the route slug inside the Action. `details` is
 * required only when `reason = OTHER`. Name/email are optional and used only
 * for an anonymous reporter.
 */
final class SubmitVacancyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(VacancyReportVocabulary::reasons())],
            'details' => ['nullable', 'string', 'max:'.VacancyReportVocabulary::MAX_DETAILS],
            'reporter_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reporter_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('reason') === 'OTHER' && trim((string) $this->input('details')) === '') {
                $validator->errors()->add('details', 'Jelaskan alasan laporan Anda.');
            }
        });
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
