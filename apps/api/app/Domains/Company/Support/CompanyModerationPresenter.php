<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use App\Domains\Company\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Career-Center review read model for the "Verifikasi Perusahaan" Inertia
 * pages (Frontend Vertical Slice v4).
 *
 * This is presentation wiring only — the caller resolves an already-authorized
 * company through `CompanyScope` and this class mutates nothing. It mirrors the
 * frozen `GET /companies/{company}` moderator view (API_CONTRACT.md: "ALLOW
 * read for Career Center", `internal_note` "visible only to Career Center,
 * Auditor, and Super Admin"). It exposes company business data and the full
 * append-only verification trail **including `internal_note`** — never
 * passwords, tokens, SMTP secrets, or candidate data.
 *
 * `mitra_kampus_active` is surfaced only so the review UI keeps "Terverifikasi"
 * (verification) visibly distinct from Mitra Kampus; verification and an active
 * campus partnership are independent facts and this slice implements no
 * partnership action.
 */
final class CompanyModerationPresenter
{
    /** Queue-row shape — the fields a Career Center reviewer scans in the list. @return array<string, mixed> */
    public static function summary(Company $company): array
    {
        $lastSubmission = $company->verificationReviews()
            ->where('action', 'SUBMIT')->orderByDesc('reviewed_at')->orderByDesc('id')
            ->value('reviewed_at');

        return [
            'id' => (int) $company->getKey(),
            'name' => $company->name,
            'slug' => $company->slug,
            'organization_type_id' => self::nullableInt($company->organization_type_id),
            'industry_id' => self::nullableInt($company->industry_id),
            'official_email' => $company->official_email,
            'city_geographic_area_id' => self::nullableInt($company->city_geographic_area_id),
            'verification_status' => $company->verification_status->value,
            'verified_at' => $company->verified_at?->toIso8601String(),
            'suspended_at' => $company->suspended_at?->toIso8601String(),
            'submitted_at' => $lastSubmission?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }

    /** Full review surface. @return array<string, mixed> */
    public static function detail(Company $company): array
    {
        return self::summary($company) + [
            'website' => $company->website,
            'official_phone' => $company->official_phone,
            'address' => $company->address,
            'province_geographic_area_id' => self::nullableInt($company->province_geographic_area_id),
            'legal_identifier' => $company->legal_identifier,
            'mitra_kampus_active' => DB::table('partnerships')
                ->where('company_id', $company->getKey())
                ->where('status', 'ACTIVE')
                ->where('start_date', '<=', now()->toDateString())
                ->where(function ($q): void {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                })
                ->exists(),
            'documents' => $company->documents()->whereNull('superseded_at')->orderBy('id')->get()
                ->map(static fn ($d): array => [
                    'id' => (int) $d->id,
                    'document_type' => $d->document_type,
                    'document_number' => $d->document_number,
                    'issued_at' => $d->issued_at?->toDateString(),
                    'expires_at' => $d->expires_at?->toDateString(),
                    'status' => $d->status,
                ])->values()->all(),
            'members' => $company->members()->active()->orderBy('id')->get()
                ->map(static fn ($m): array => [
                    'id' => (int) $m->id,
                    'user_id' => (int) $m->user_id,
                    'company_role' => $m->company_role,
                    'status' => $m->status,
                ])->values()->all(),
            // Full trail — Career Center is authorised to see internal_note.
            'verification_history' => $company->verificationReviews()
                ->orderBy('reviewed_at')->orderBy('id')->get()
                ->map(static fn ($r): array => [
                    'id' => (int) $r->id,
                    'action' => $r->action instanceof \BackedEnum ? $r->action->value : $r->action,
                    'from_status' => $r->from_status,
                    'to_status' => $r->to_status,
                    'reason_category' => $r->reason_category,
                    'recruiter_visible_note' => $r->recruiter_visible_note,
                    'internal_note' => $r->internal_note,
                    'reviewed_at' => $r->reviewed_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    /**
     * The frozen `ReviewCompanyVerification` transition graph, as an ordered
     * list of route segments eligible from the current status. Display hint
     * only — `CompanyPolicy::review` and the Action remain the authority.
     *
     * @return list<string>
     */
    public static function eligibleActions(Company $company): array
    {
        return match ($company->verification_status->value) {
            'PENDING_VERIFICATION' => ['verify', 'request-revision', 'reject'],
            'VERIFIED' => ['suspend'],
            'SUSPENDED' => ['restore'],
            default => [],
        };
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
