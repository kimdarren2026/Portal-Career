<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;

final class SyncCandidateOrganizationsRequest extends CandidateCollectionRequest
{
    protected function itemFields(): array { return ['id', 'organization_name', 'role_title', 'organization_type', 'start_date', 'end_date', 'is_current', 'description']; }
    protected function itemRules(): array { return ['items.*.organization_name' => ['required', 'string', 'max:255'], 'items.*.role_title' => ['required', 'string', 'max:255'], 'items.*.organization_type' => ['nullable', 'string', 'max:64'], 'items.*.start_date' => ['nullable', 'date_format:Y-m-d'], 'items.*.end_date' => ['nullable', 'date_format:Y-m-d'], 'items.*.is_current' => ['required', 'boolean'], 'items.*.description' => ['nullable', 'string']]; }
    protected function validateSemantics(Validator $validator): void { foreach (array_keys((array) $this->input('items', [])) as $index) { $this->validateTimeline($validator, $index); } }
}
