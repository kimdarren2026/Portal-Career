<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;

final class UpdateCandidateDocumentRequest extends CandidateFormRequest
{
    public function rules(): array { return ['display_name' => ['sometimes', 'nullable', 'string', 'max:255'], 'document_type' => ['sometimes', 'nullable', 'string', 'max:64']]; }
    public function withValidator(Validator $validator): void { $validator->after(function (Validator $validator): void { foreach (array_keys($this->all()) as $field) { if (! in_array($field, ['display_name', 'document_type'], true)) { $validator->errors()->add($field, 'Field tidak didukung.'); } } }); }
}
