<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Vacancy\Models\Vacancy;

/**
 * Full logical snapshot for `vacancy_versions.snapshot` — "Technology-neutral
 * structured snapshot of the complete vacancy revision, sufficient to
 * reconstruct the before/after state required by FR-VAC-007"
 * (`DATA_DICTIONARY.md`).
 *
 * Carries the vacancy's own logical state plus its child collections. It never
 * carries moderation notes: `internal_note` lives on
 * `vacancy_moderation_reviews`, is invisible to recruiters, and is not part of
 * the vacancy revision a recruiter authored.
 */
final class VacancySnapshot
{
    /** @return array<string, mixed> */
    public static function of(Vacancy $vacancy): array
    {
        // Reloaded, never loadMissing: after a requirement replacement a cached
        // relation would snapshot the pre-sync collection (VA-1).
        $vacancy->load(['requirements', 'screeningQuestions']);

        return [
            'vacancy' => [
                'vacancy_code' => $vacancy->vacancy_code,
                'slug' => $vacancy->slug,
                'vacancy_type' => $vacancy->vacancy_type?->value,
                'ownership_type' => $vacancy->ownership_type,
                'company_id' => self::nullableInt($vacancy->company_id),
                'organizational_unit_id' => self::nullableInt($vacancy->organizational_unit_id),
                'title' => $vacancy->title,
                'description' => $vacancy->description,
                'responsibilities' => $vacancy->responsibilities,
                'employment_type' => $vacancy->employment_type,
                'workplace_mode' => $vacancy->workplace_mode,
                'province_geographic_area_id' => self::nullableInt($vacancy->province_geographic_area_id),
                'city_geographic_area_id' => self::nullableInt($vacancy->city_geographic_area_id),
                'location' => $vacancy->location,
                'openings_count' => $vacancy->openings_count,
                'minimum_education' => $vacancy->minimum_education,
                'experience_requirement' => $vacancy->experience_requirement,
                'salary_min' => $vacancy->salary_min,
                'salary_max' => $vacancy->salary_max,
                'salary_currency' => $vacancy->salary_currency,
                'target_audience' => $vacancy->target_audience?->value,
                'application_method' => $vacancy->application_method?->value,
                'external_ats_url' => $vacancy->external_ats_url,
                'open_at' => $vacancy->open_at?->toIso8601String(),
                'close_at' => $vacancy->close_at?->toIso8601String(),
                'current_status' => $vacancy->current_status?->value,
            ],
            'requirements' => $vacancy->requirements
                ->sortBy(['sort_order', 'id'])
                ->map(static fn ($requirement): array => [
                    'requirement_type' => $requirement->requirement_type?->value,
                    'education_level' => $requirement->education_level,
                    'study_program_id' => self::nullableInt($requirement->study_program_id),
                    'skill_id' => self::nullableInt($requirement->skill_id),
                    'minimum_years_experience' => $requirement->minimum_years_experience,
                    'value_text' => $requirement->value_text,
                    'note' => $requirement->note,
                    'required' => $requirement->required,
                    'sort_order' => $requirement->sort_order,
                ])->values()->all(),
            'screening_questions' => $vacancy->screeningQuestions
                ->sortBy(['sort_order', 'id'])
                ->map(static fn ($question): array => [
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type?->value,
                    'required' => $question->required,
                    'options_definition' => $question->options_definition,
                    'sort_order' => $question->sort_order,
                    'active' => $question->active,
                ])->values()->all(),
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
