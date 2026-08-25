<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Vacancy\Enums\ApplicationMethod;
use App\Domains\Vacancy\Models\Vacancy;

/**
 * The B-5 submit-completeness gate, in one place.
 *
 * It reads the CURRENTLY PERSISTED vacancy — never a request body — and mutates
 * nothing. DRAFT and REVISION_REQUIRED are allowed to be incomplete while
 * editing; completeness is enforced only when submitting for review.
 *
 * Zero is a legal cardinality for every optional collection: study programs,
 * skills, additional qualifications and screening questions. No minimum-one
 * rule exists for any of them, and salary is never part of this gate.
 */
final class VacancySubmissionCompleteness
{
    /** Stable semantic identifiers returned in `details.missing`. */
    private const SCALAR_CATEGORIES = [
        'title' => 'title',
        'description' => 'description',
        'responsibilities' => 'qualifications',
        'employment_type' => 'employment_type',
        'location' => 'location',
        'workplace_mode' => 'workplace_mode',
        'openings_count' => 'openings_count',
        'minimum_education' => 'minimum_education',
        'experience_requirement' => 'experience_requirement',
        'target_audience' => 'target_audience',
        'application_method' => 'application_method',
    ];

    /** @return list<string> Missing category identifiers, deterministic in declaration order. */
    public function missing(Vacancy $vacancy): array
    {
        $missing = [];

        foreach (self::SCALAR_CATEGORIES as $column => $category) {
            if ($this->isBlank($vacancy->getAttribute($column))) {
                $missing[] = $category;
            }
        }

        // Location may be satisfied either by the master reference or by the
        // frozen free-text fallback; both are legitimate representations.
        if (in_array('location', $missing, true) && $vacancy->city_geographic_area_id !== null) {
            $missing = array_values(array_diff($missing, ['location']));
        }

        return $missing;
    }

    /** Dates carry their own frozen codes, so they are reported separately from the category list. */
    public function datesMissing(Vacancy $vacancy): bool
    {
        return $vacancy->open_at === null || $vacancy->close_at === null;
    }

    public function closeBeforeOpen(Vacancy $vacancy): bool
    {
        return $vacancy->open_at !== null
            && $vacancy->close_at !== null
            && $vacancy->close_at <= $vacancy->open_at;
    }

    public function externalUrlMissing(Vacancy $vacancy): bool
    {
        return $vacancy->application_method === ApplicationMethod::ExternalAts
            && $this->isBlank($vacancy->external_ats_url);
    }

    public function externalUrlInvalid(Vacancy $vacancy): bool
    {
        if ($vacancy->application_method !== ApplicationMethod::ExternalAts || $this->isBlank($vacancy->external_ats_url)) {
            return false;
        }

        return ! str_starts_with(mb_strtolower(trim((string) $vacancy->external_ats_url)), 'https://');
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
