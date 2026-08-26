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
 * A vacancy is publicly visible only when ALL of the following hold:
 *   - ownership_type = COMPANY (campus vacancies are a separate future phase)
 *   - current_status = PUBLISHED
 *   - open_at <= now < close_at (independently enforced here — never assumed
 *     from the O-7 scheduler, which may not have run yet)
 *   - target_audience <> INTERNAL (visibility, not eligibility: ALUMNI_ONLY
 *     and FINAL_YEAR_AND_ALUMNI remain publicly discoverable — INV-028)
 *   - the owning company's verification_status = VERIFIED (PD-1). Partnership
 *     (`mitra_kampus_active`) is explicitly NOT part of this predicate.
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
            ->where('ownership_type', 'COMPANY')
            ->where('current_status', VacancyStatus::Published)
            ->where('open_at', '<=', $now)
            ->where('close_at', '>', $now)
            ->where('target_audience', '!=', TargetAudience::Internal->value)
            ->whereHas('company', fn (Builder $q) => $q->where('verification_status', 'VERIFIED'));
    }
}
