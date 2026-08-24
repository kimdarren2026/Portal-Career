<?php

declare(strict_types=1);

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class UpdateCandidateProfileRequest extends CandidateFormRequest
{
    private const FIELDS = [
        'headline', 'phone', 'summary', 'province_geographic_area_id',
        'city_geographic_area_id', 'province', 'city',
        'preferred_employment_type', 'preferred_workplace_mode',
        'preferred_location_note', 'open_to_opportunities',
    ];

    public function rules(): array
    {
        $activeArea = Rule::exists('geographic_areas', 'id')->where('active', true);

        return [
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'province_geographic_area_id' => ['sometimes', 'nullable', 'integer', $activeArea],
            'city_geographic_area_id' => ['sometimes', 'nullable', 'integer', $activeArea],
            'province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Vocabulary approval is pending: type and column length only.
            'preferred_employment_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'preferred_workplace_mode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'preferred_location_note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'open_to_opportunities' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, self::FIELDS, true)) {
                    $validator->errors()->add($field, 'Field tidak didukung.');
                }
            }

            foreach ([
                ['province_geographic_area_id', 'province'],
                ['city_geographic_area_id', 'city'],
            ] as [$masterField, $fallbackField]) {
                if ($this->filled($masterField) && $this->filled($fallbackField)) {
                    $validator->errors()->add($fallbackField, 'Gunakan area master atau teks bebas sebagai fallback, bukan keduanya.');
                }
            }
        });
    }
}
