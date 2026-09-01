<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * `CAMPUS_SCOPE` (Admin Kepegawaian / `HR_ADMIN`) — activated by the approved
 * Product Owner / SPEC-DOC decision (see API_CONTRACT.md Part X). BRD/FSD
 * already require the Karier di Kampus track (FSD §5.5, FR-HR-001..007); this
 * class only wires the previously-deferred runtime.
 *
 * CAMPUS_SCOPE reaches vacancies where `ownership_type = CAMPUS`, and their
 * applications and children — never a `COMPANY` vacancy. There is no company
 * membership involved: a campus admin sees every campus vacancy (the frozen
 * matrix's "optionally narrowed by organizational_unit_id" narrowing is not
 * implemented — there is no unit-membership table — so the baseline is all
 * campus). This scope grants nothing on the company track, exactly as
 * `COMPANY_SCOPE` grants nothing on the campus track.
 */
final class CampusScope
{
    public static function isCampusAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::HrAdmin);
    }

    /** A `Vacancy` query narrowed to campus-owned rows. */
    public static function vacancyQuery(): Builder
    {
        return \App\Domains\Vacancy\Models\Vacancy::query()->where('ownership_type', 'CAMPUS');
    }

    /**
     * Applies the campus predicate to a related builder that reaches a vacancy
     * through `$relation` (e.g. `application.vacancy`).
     *
     * @template T of Builder
     * @param  T  $query
     * @return T
     */
    public static function throughVacancy(Builder $query, string $relation): Builder
    {
        return $query->whereHas($relation, fn (Builder $q) => $q->where('ownership_type', 'CAMPUS'));
    }
}
