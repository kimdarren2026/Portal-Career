<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyRequirement;
use App\Domains\Vacancy\Models\VacancyScreeningQuestion;
use App\Domains\Vacancy\Models\VacancyVersion;

/**
 * Owner and moderator read models.
 *
 * `internal_note` lives on the moderation trail and is never returned to a
 * recruiter. This phase reads no moderation rows at all, so the flag exists to
 * keep that boundary explicit for the reader rather than to gate a field.
 */
final class VacancyPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Vacancy $vacancy, bool $internal = false): array
    {
        return [
            'id' => (int) $vacancy->getKey(),
            'vacancy_code' => $vacancy->vacancy_code,
            'slug' => $vacancy->slug,
            'title' => $vacancy->title,
            'vacancy_type' => $vacancy->vacancy_type?->value,
            'ownership_type' => $vacancy->ownership_type,
            'company_id' => $vacancy->company_id === null ? null : (int) $vacancy->company_id,
            'current_status' => $vacancy->current_status?->value,
            'target_audience' => $vacancy->target_audience?->value,
            'application_method' => $vacancy->application_method?->value,
            'open_at' => $vacancy->open_at?->toIso8601String(),
            'close_at' => $vacancy->close_at?->toIso8601String(),
            'published_at' => $vacancy->published_at?->toIso8601String(),
            'created_at' => $vacancy->created_at?->toIso8601String(),
        ];
    }

    /** @param list<string> $include @return array<string, mixed> */
    public static function detail(Vacancy $vacancy, array $include = [], bool $internal = false): array
    {
        $data = self::summary($vacancy, $internal) + [
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
            'external_ats_url' => $vacancy->external_ats_url,
            'closed_at' => $vacancy->closed_at?->toIso8601String(),
            'suspended_at' => $vacancy->suspended_at?->toIso8601String(),
            'current_version' => (int) VacancyVersion::query()->where('vacancy_id', $vacancy->getKey())->max('version_number'),
        ];

        if (in_array('requirements', $include, true)) {
            $data['requirements'] = $vacancy->requirements()->orderBy('sort_order')->orderBy('id')
                ->get()->map(self::requirement(...))->values()->all();
        }
        if (in_array('screening_questions', $include, true)) {
            $data['screening_questions'] = $vacancy->screeningQuestions()->orderBy('sort_order')->orderBy('id')
                ->get()->map(self::screeningQuestion(...))->values()->all();
        }
        if (in_array('versions', $include, true)) {
            $data['versions'] = $vacancy->versions()->orderBy('version_number')
                ->get()->map(static fn (VacancyVersion $version): array => [
                    'version_number' => $version->version_number,
                    'created_at' => $version->created_at?->toIso8601String(),
                ])->values()->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function requirement(VacancyRequirement $requirement): array
    {
        return [
            'id' => (int) $requirement->getKey(),
            'requirement_type' => $requirement->requirement_type?->value,
            'education_level' => $requirement->education_level,
            'study_program_id' => self::nullableInt($requirement->study_program_id),
            'skill_id' => self::nullableInt($requirement->skill_id),
            'minimum_years_experience' => $requirement->minimum_years_experience,
            'value_text' => $requirement->value_text,
            'note' => $requirement->note,
            'required' => $requirement->required,
            'sort_order' => $requirement->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    public static function screeningQuestion(VacancyScreeningQuestion $question): array
    {
        return [
            'id' => (int) $question->getKey(),
            'question_text' => $question->question_text,
            'question_type' => $question->question_type?->value,
            'required' => $question->required,
            'options_definition' => $question->options_definition,
            'sort_order' => $question->sort_order,
            'active' => $question->active,
        ];
    }

    /** @return array<string, mixed> */
    public static function stage(RecruitmentStage $stage): array
    {
        return [
            'id' => (int) $stage->getKey(),
            'name' => $stage->name,
            'stage_type' => $stage->stage_type,
            'sort_order' => $stage->sort_order,
            'active' => $stage->active,
            'candidate_visible_label' => $stage->candidate_visible_label,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
