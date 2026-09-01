<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Vacancy\Enums\TargetAudience;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The single public-visibility predicate for company vacancy discovery
 * (O-7 documentation §"Public vacancy discovery" and PD-1). Listing and
 * detail both consume this and only this — the predicate is never
 * re-implemented or weakened on either surface (PD-1 approved decision).
 *
 * Common to BOTH tracks, a vacancy is publicly visible only when ALL of:
 *   - current_status = PUBLISHED
 *   - open_at <= now < close_at (independently enforced here — never assumed
 *     from the scheduler, which may not have run yet)
 *   - target_audience <> INTERNAL (visibility, not eligibility: ALUMNI_ONLY
 *     and FINAL_YEAR_AND_ALUMNI remain publicly discoverable — INV-028)
 *
 * Then, per track:
 *   - ownership_type = COMPANY: the owning company's verification_status =
 *     VERIFIED (PD-1). Partnership (`mitra_kampus_active`) is explicitly NOT
 *     part of this predicate.
 *   - ownership_type = CAMPUS (Karier di Kampus): no company gate exists —
 *     a campus vacancy has no company (FR-HR-001). Activated by the approved
 *     PO / SPEC-DOC campus decision. The COMPANY branch is byte-identical to
 *     PD-1 and is never weakened; campus is added as a parallel branch.
 *
 * This is an application-level filter, not an index. `idx_vacancies_public_listing`
 * and `idx_vacancies_public_filters` are performance aids only and must never
 * be trusted as the authorization boundary.
 */
final class PublicVacancyScope
{
    public static function query(?Carbon $now = null): Builder
    {
        $now ??= now();

        return Vacancy::query()
            ->where('current_status', VacancyStatus::Published)
            ->where('open_at', '<=', $now)
            ->where('close_at', '>', $now)
            ->where('target_audience', '!=', TargetAudience::Internal->value)
            ->where(function (Builder $q): void {
                // PD-1 (company), unchanged.
                $q->where(fn (Builder $c) => $c
                    ->where('ownership_type', 'COMPANY')
                    ->whereHas('company', fn (Builder $co) => $co->where('verification_status', 'VERIFIED')))
                    // Karier di Kampus (campus) — no company verification gate.
                    ->orWhere('ownership_type', 'CAMPUS');
            });
    }
}
