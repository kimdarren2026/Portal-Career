<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;

/**
 * Resolves whether an actor may reach a given application for the purpose of
 * downloading a document shared with it (PGC-V1 / PD-A, AUTHORIZATION_MATRIX.md
 * §4.6). Precedence mirrors `ApplicationController`:
 *
 *  - CANDIDATE — OWN application (candidate scope).
 *  - COMPANY_RECRUITER / COMPANY_ADMIN — COMPANY_SCOPE; HR_ADMIN — CAMPUS_SCOPE;
 *    SUPER_ADMIN — ALLOW. All three via `RecruiterApplicationScope`.
 *  - SELECTOR — active ASSIGNED_STAGE via `SelectorApplicationScope`.
 *
 * CAREER_CENTER and AUDITOR are DENIED — no branch resolves them, and an
 * unresolved actor gets `null` (the caller turns that into an enumeration-safe
 * 404, never a hint that the row exists).
 */
final class ApplicationDocumentAccess
{
    public function __construct(private readonly ApplicationScope $candidateScope) {}

    public function resolveApplicationFor(User $actor, int $applicationId): ?Application
    {
        if (RecruiterApplicationScope::isRecruiterOrAdmin($actor)
            || RecruiterApplicationScope::isSuperAdmin($actor)
            || \App\Domains\Vacancy\Support\CampusScope::isCampusAdmin($actor)) {
            $model = RecruiterApplicationScope::findFor($actor, $applicationId);
            if ($model !== null) {
                return $model;
            }
        }

        if (ApplicationScope::isCandidateActor($actor)) {
            $model = $this->candidateScope->findFor($actor, $applicationId);
            if ($model !== null) {
                return $model;
            }
        }

        if (SelectorApplicationScope::isSelectorActor($actor)) {
            $model = SelectorApplicationScope::findFor($actor, $applicationId);
            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }
}
