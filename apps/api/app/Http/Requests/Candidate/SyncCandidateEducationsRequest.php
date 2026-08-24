<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class SyncCandidateEducationsRequest extends CandidateCollectionRequest
{
    protected function itemFields(): array { return ['id', 'institution_name', 'study_program_id', 'study_program_name', 'education_level', 'start_date', 'graduation_date', 'graduation_year', 'score_summary']; }
    protected function itemRules(): array
    {
        return [
            'items.*.institution_name' => ['required', 'string', 'max:255'],
            'items.*.study_program_id' => ['nullable', 'integer', Rule::exists('study_programs', 'id')->where('active', true)],
            'items.*.study_program_name' => ['nullable', 'string', 'max:255'],
            'items.*.education_level' => ['required', 'string', 'max:64'],
            'items.*.start_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.graduation_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.graduation_year' => ['nullable', 'integer'],
            'items.*.score_summary' => ['nullable', 'string', 'max:255'],
        ];
    }
    protected function validateSemantics(Validator $validator): void
    {
        foreach ((array) $this->input('items', []) as $index => $item) {
            if (is_array($item) && ! empty($item['start_date']) && ! empty($item['graduation_date']) && $item['graduation_date'] < $item['start_date']) {
                $validator->errors()->add("items.$index.graduation_date", 'Tanggal kelulusan tidak boleh sebelum tanggal mulai.');
            }
        }
    }
}
