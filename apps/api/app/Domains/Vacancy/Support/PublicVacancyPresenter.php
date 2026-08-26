<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Partnership\Support\PartnershipStatus;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyRequirement;

/**
 * Public read model for anonymous vacancy discovery (FSD §4.1, API_CONTRACT.md
 * "GET /api/v1/public/vacancies" and ".../{slug}"). Every field here is a
 * deliberate allow-list — nothing internal is ever forwarded by omission.
 *
 * Explicitly and permanently excluded from both list and detail: moderation
 * notes of any kind, `internal_note`, `created_by`, reviewer identities,
 * company legal documents/members, applicant data or counts, screening
 * questions, the raw `external_ats_url`, salary (OP-03 remains open), and
 * `logo_storage_reference` (private-storage key — the public asset policy is
 * still undecided, so the summary carries no image reference at all yet).
 */
final class PublicVacancyPresenter
{
    /**
     * @param array<int, bool>|null $mitraLookup Precomputed per-company partnership
     * flags for a batch (listing) call. Pass null for a single-row (detail) call,
     * where one extra query is an acceptable, bounded cost.
     *
     * @return array<string, mixed>
     */
    public static function summary(Vacancy $vacancy, ?array $mitraLookup = null): array
    {
        return [
            'slug' => $vacancy->slug,
            'title' => $vacancy->title,
            'vacancy_type' => $vacancy->vacancy_type?->value,
            'employment_type' => $vacancy->employment_type,
            'workplace_mode' => $vacancy->workplace_mode,
            'location' => $vacancy->location,
            'province_geographic_area_id' => self::nullableInt($vacancy->province_geographic_area_id),
            'city_geographic_area_id' => self::nullableInt($vacancy->city_geographic_area_id),
            'target_audience' => $vacancy->target_audience?->value,
            'application_method' => $vacancy->application_method?->value,
            'published_at' => $vacancy->published_at?->toIso8601String(),
            'close_at' => $vacancy->close_at?->toIso8601String(),
            'company' => self::companySummary($vacancy, $mitraLookup),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Vacancy $vacancy): array
    {
        return self::summary($vacancy, null) + [
            'description' => $vacancy->description,
            'responsibilities' => $vacancy->responsibilities,
            'openings_count' => $vacancy->openings_count,
            'minimum_education' => $vacancy->minimum_education,
            'experience_requirement' => $vacancy->experience_requirement,
            // The raw destination is deliberately absent: FR-EXT-001 requires the
            // click to flow through the (not-yet-implemented) tracked
            // external-apply-start capability, never a bare crawlable link.
            'applies_externally' => $vacancy->application_method?->value === 'EXTERNAL_ATS',
            'requirements' => $vacancy->requirements()->orderBy('sort_order')->orderBy('id')
                ->get()->map(self::requirement(...))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function requirement(VacancyRequirement $requirement): array
    {
        return [
            'requirement_type' => $requirement->requirement_type?->value,
            'education_level' => $requirement->education_level,
            'study_program_id' => self::nullableInt($requirement->study_program_id),
            'skill_id' => self::nullableInt($requirement->skill_id),
            'minimum_years_experience' => $requirement->minimum_years_experience,
            'value_text' => $requirement->value_text,
            'required' => $requirement->required,
            'sort_order' => $requirement->sort_order,
        ];
    }

    /**
     * Exactly the frozen public company summary (API_CONTRACT.md line 2381):
     * name, logo, industry, city, and derived `mitra_kampus_active`. No
     * `verification_status` field is exposed — VERIFIED is already required
     * for visibility (PD-1); the status value itself is never displayed.
     *
     * @param array<int, bool>|null $mitraLookup
     * @return array<string, mixed>
     */
    public static function companySummary(Vacancy $vacancy, ?array $mitraLookup = null): array
    {
        $company = $vacancy->company;
        if ($company === null) {
            return [];
        }
        $companyId = (int) $company->getKey();

        return [
            'company_id' => $companyId,
            'name' => $company->name,
            // The public logo-serving mechanism is undecided (private storage
            // baseline, no signed/public derivative contract exists yet). The
            // private storage key is never exposed; textual discovery does not
            // wait on this decision.
            'logo_url' => null,
            'industry_id' => self::nullableInt($company->industry_id),
            'city_geographic_area_id' => self::nullableInt($company->city_geographic_area_id),
            'mitra_kampus_active' => $mitraLookup[$companyId] ?? PartnershipStatus::isActiveFor($companyId),
        ];
    }

    /**
     * Batch partnership-active lookup for a set of companies in a single
     * query, so listing N vacancies never issues N partnership queries.
     *
     * @param list<int> $companyIds
     * @return array<int, bool>
     */
    public static function batchMitraKampusActive(array $companyIds): array
    {
        return PartnershipStatus::batchActive($companyIds);
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
