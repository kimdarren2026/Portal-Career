<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class SyncCandidateSkillsRequest extends CandidateCollectionRequest
{
    protected function itemFields(): array { return ['id', 'skill_id', 'proficiency_level']; }
    protected function itemRules(): array { return ['items.*.skill_id' => ['required', 'integer', Rule::exists('skills', 'id')->where('active', true)], 'items.*.proficiency_level' => ['nullable', 'string', 'max:64']]; }
    protected function validateSemantics(Validator $validator): void { $skills = []; foreach ((array) $this->input('items', []) as $index => $item) { if (! is_array($item) || ! isset($item['skill_id'])) { continue; } if (isset($skills[(string) $item['skill_id']])) { $validator->errors()->add("items.$index.skill_id", 'Keterampilan tidak boleh duplikat.'); } $skills[(string) $item['skill_id']] = true; } }
}
