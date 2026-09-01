<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use App\Domains\Company\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Recruiter-safe read model for the "Profil Perusahaan" and "Status Verifikasi"
 * Inertia pages (Recruiter Company & Vacancy Frontend Slice v3).
 *
 * This is presentation wiring only — it reads an already-authorized company row
 * (the caller resolves it through `CompanyScope`) and mutates nothing. It never
 * exposes `internal_note` or any Career-Center-internal verification field: the
 * recruiter sees exactly `reason_category` and `recruiter_visible_note`, the
 * same boundary `CompanyController::present()` already draws for a non-internal
 * viewer.
 *
 * `mitra_kampus_active` is surfaced only so the UI can keep "Terverifikasi"
 * (verification) visibly distinct from partnership; verification and an active
 * campus partnership are independent facts.
 */
final class CompanyProfilePresenter
{
    /** Profile fields a recruiter may see and edit. Legal/internal columns are omitted. @return array<string, mixed> */
    public static function profile(Company $company): array
    {
        return [
            'id' => (int) $company->getKey(),
            'name' => $company->name,
            'slug' => $company->slug,
            'organization_type_id' => self::nullableInt($company->organization_type_id),
            'industry_id' => self::nullableInt($company->industry_id),
            'website' => $company->website,
            'official_email' => $company->official_email,
            'official_phone' => $company->official_phone,
            'address' => $company->address,
            'province_geographic_area_id' => self::nullableInt($company->province_geographic_area_id),
            'city_geographic_area_id' => self::nullableInt($company->city_geographic_area_id),
            'legal_identifier' => $company->legal_identifier,
            'verification_status' => $company->verification_status->value,
            'verified_at' => $company->verified_at?->toIso8601String(),
            'suspended_at' => $company->suspended_at?->toIso8601String(),
            // Verification is not partnership: shown so the UI never conflates the two.
            'mitra_kampus_active' => self::partnershipActive((int) $company->getKey()),
        ];
    }

    /**
     * Recruiter-visible verification trail for "Status Verifikasi".
     * `internal_note` is never selected. Ordered oldest → newest.
     *
     * @return list<array<string, mixed>>
     */
    public static function verificationTrail(Company $company): array
    {
        return $company->verificationReviews()
            ->orderBy('reviewed_at')->orderBy('id')
            ->get(['action', 'from_status', 'to_status', 'reason_category', 'recruiter_visible_note', 'reviewed_at'])
            ->map(static fn ($review): array => [
                'action' => $review->action instanceof \BackedEnum ? $review->action->value : $review->action,
                'from_status' => $review->from_status,
                'to_status' => $review->to_status,
                'reason_category' => $review->reason_category,
                'recruiter_visible_note' => $review->recruiter_visible_note,
                'reviewed_at' => $review->reviewed_at?->toIso8601String(),
            ])
            ->values()->all();
    }

    private static function partnershipActive(int $companyId): bool
    {
        return DB::table('partnerships')
            ->where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($q): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->exists();
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
