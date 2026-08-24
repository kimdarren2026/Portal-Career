<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

final class UploadCandidateDocumentRequest extends CandidateFormRequest
{
    public const MAX_BYTES = 10_485_760;

    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'document_type' => ['required', 'string', 'max:64'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, ['file', 'document_type', 'display_name'], true)) {
                    $validator->errors()->add($field, 'Field tidak didukung.');
                }
            }

            $file = $this->file('file');
            if ($file instanceof UploadedFile && (int) $file->getSize() > self::MAX_BYTES) {
                $validator->errors()->add('file', 'Ukuran dokumen melebihi batas 10 MiB.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('file');
        if ($file instanceof UploadedFile && (int) $file->getSize() > self::MAX_BYTES) {
            throw new HttpResponseException(ContractResponse::error(
                $this,
                'PAYLOAD_TOO_LARGE',
                413,
                'Ukuran dokumen melebihi batas 10 MiB.',
            ));
        }

        parent::failedValidation($validator);
    }
}
