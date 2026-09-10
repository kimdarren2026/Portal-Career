<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Domains\Company\Support\CompanyLegalDocumentPolicy;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `POST /companies/{company}/documents` transport shape (PGC-V1 / PD-D).
 * Content/MIME is re-inspected server-side inside `UploadCompanyDocument`;
 * these rules are the first, cheap gate.
 */
final class UploadCompanyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) (CompanyLegalDocumentPolicy::MAX_BYTES / 1024)],
            'document_type' => ['required', 'string', Rule::in(CompanyLegalDocumentPolicy::types())],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
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
