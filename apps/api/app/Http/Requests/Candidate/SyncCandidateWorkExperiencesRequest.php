<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;

final class SyncCandidateWorkExperiencesRequest extends CandidateCollectionRequest
{
    protected function itemFields(): array { return ['id', 'employer_name', 'position_title', 'employment_type', 'start_date', 'end_date', 'is_current', 'description']; }
    protected function itemRules(): array { return ['items.*.employer_name' => ['required', 'string', 'max:255'], 'items.*.position_title' => ['required', 'string', 'max:255'], 'items.*.employment_type' => ['nullable', 'string', 'max:64'], 'items.*.start_date' => ['nullable', 'date_format:Y-m-d'], 'items.*.end_date' => ['nullable', 'date_format:Y-m-d'], 'items.*.is_current' => ['required', 'boolean'], 'items.*.description' => ['nullable', 'string']]; }
    protected function validateSemantics(Validator $validator): void { foreach (array_keys((array) $this->input('items', [])) as $index) { $this->validateTimeline($validator, $index); } }
}
