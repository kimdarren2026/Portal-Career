<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;

final class ListCandidateDocumentsRequest extends CandidateFormRequest
{
    public function rules(): array { return ['document_type' => ['nullable', 'string', 'max:64'], 'include_archived' => ['nullable', 'boolean'], 'sort' => ['nullable', 'in:uploaded_at,display_name'], 'page' => ['nullable', 'integer', 'min:1']]; }
    public function withValidator(Validator $validator): void { $validator->after(function (Validator $validator): void { foreach (array_keys($this->query()) as $field) { if (! in_array($field, ['document_type', 'include_archived', 'sort', 'page'], true)) { $validator->errors()->add($field, 'Filter tidak didukung.'); } } }); }
}
