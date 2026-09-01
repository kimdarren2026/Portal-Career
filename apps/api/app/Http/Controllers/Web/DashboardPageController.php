<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Outcome\Queries\ListIncompleteRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Support\RecruitmentOutcomeScope;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleScope;
use App\Domains\Vacancy\Support\VacancyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * `GET /dashboard` — landing page per persona (Frontend Vertical Slice v1;
 * recruiter operational snapshot added in v5).
 *
 * Every recruiter figure is a literal count over a scope already frozen
 * elsewhere — no conversion rate, success score, SLA, benchmark or
 * Time-to-Fill is derived here. The six figures map 1:1 to FR-REP-001
 * (Dashboard Recruiter): company verification status, vacancy by status,
 * applicants, upcoming schedules, revision requests, missing-outcome
 * reminder. All counts are server-scoped to the actor's active company
 * membership (or global for SUPER_ADMIN, exactly as the reused scopes
 * already resolve); nothing is loaded unscoped and filtered afterwards.
 */
final class DashboardPageController extends CandidateController
{
    public function __construct(CandidateProfileResolver $profiles) { parent::__construct($profiles); }

    public function index(Request $request, ListIncompleteRecruitmentOutcomes $incompleteOutcomes): Response|SymfonyResponse
    {
        $actor = $this->actor($request);

        // Career Center Frontend Slice v9 — the "Dashboard" nav destination
        // for the Career Center persona. Checked before the recruiter branch
        // so a Career Center actor is never mis-routed to a recruiter view.
        if ($actor->hasActiveRole(RoleCode::CareerCenterStaff) || $actor->hasActiveRole(RoleCode::CareerCenterManager)) {
            return Inertia::render('career-center/Dashboard', $this->careerCenterSnapshot($actor));
        }

        // Recruiter branch is membership/role based (CompanyRecruiter |
        // CompanyAdmin). A Super Admin who also holds an ACTIVE company
        // membership matches here and keeps the recruiter dashboard, exactly
        // as before; a pure Super Admin does NOT — see the redirect below.
        if (RecruiterApplicationScope::isRecruiterOrAdmin($actor)) {
            return Inertia::render('recruiter/Dashboard', $this->recruiterSnapshot($actor, $incompleteOutcomes));
        }

        // Super Admin Control Plane v10 — the canonical Super Admin navigation
        // has no "Dashboard" item (FSD §4.6). A pure Super Admin landing on the
        // shared `/dashboard` is redirected to the first ACTIVE canonical
        // Super Admin module rather than rendered a recruiter persona page.
        if (RecruiterApplicationScope::isSuperAdmin($actor)) {
            return redirect()->route('pages.super-admin.audit-log');
        }

        if (ApplicationScope::isCandidateActor($actor)) {
            $profile = $this->ownProfile($request);

            return Inertia::render('candidate/Dashboard', [
                'counts' => [
                    'applications' => DB::table('applications')->where('candidate_profile_id', $profile->getKey())->count(),
                    'schedules_upcoming' => DB::table('selection_schedules')
                        ->join('applications', 'applications.id', '=', 'selection_schedules.application_id')
                        ->where('applications.candidate_profile_id', $profile->getKey())
                        ->where('selection_schedules.status', 'SCHEDULED')
                        ->where('selection_schedules.starts_at', '>=', now())
                        ->count(),
                ],
            ]);
        }

        return Inertia::render('candidate/Dashboard', ['counts' => []]);
    }

    /**
     * FR-REP-002 operational snapshot for Career Center — the subset of the
     * FR-REP-002 list that maps to a literal count over data this persona is
     * already authorised to read (`CompanyScope` / `VacancyScope` are both
     * global readers for Career Center). Nothing is invented: "active Mitra
     * Kampus", "alumni in process/hired", "external apply started vs
     * confirmed" and "incomplete outcome" are FR-REP-002 items whose runtime
     * is deferred (Partnership, alumni verification / RC-2, External Apply),
     * so they are omitted here rather than fabricated. No risk score, SLA,
     * verification-performance or approval-rate metric is derived.
     *
     * @return array<string, mixed>
     */
    private function careerCenterSnapshot(User $actor): array
    {
        /** @var array<string, int> $companiesByStatus */
        $companiesByStatus = CompanyScope::queryFor($actor)
            ->select('verification_status', DB::raw('count(*) as aggregate'))
            ->groupBy('verification_status')
            ->pluck('aggregate', 'verification_status')
            ->map(static fn ($c): int => (int) $c)->all();

        /** @var array<string, int> $vacanciesByStatus */
        $vacanciesByStatus = VacancyScope::queryFor($actor)
            ->select('current_status', DB::raw('count(*) as aggregate'))
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status')
            ->map(static fn ($c): int => (int) $c)->all();

        return [
            'companies_by_status' => $companiesByStatus,
            'vacancies_by_status' => $vacanciesByStatus,
            'counts' => [
                'verification_queue' => $companiesByStatus['PENDING_VERIFICATION'] ?? 0,
                'company_revision_required' => $companiesByStatus['REVISION_REQUIRED'] ?? 0,
                'verified_companies' => $companiesByStatus['VERIFIED'] ?? 0,
                'moderation_queue' => $vacanciesByStatus['PENDING_REVIEW'] ?? 0,
                'vacancy_revision_required' => $vacanciesByStatus['REVISION_REQUIRED'] ?? 0,
                'published_vacancies' => $vacanciesByStatus['PUBLISHED'] ?? 0,
            ],
        ];
    }

    /**
     * FR-REP-001 operational snapshot. `vacancies_by_status` is one grouped
     * query over the frozen company-scoped `VacancyScope`; `revision_requests`
     * is read straight out of that same map (no extra query, no invented
     * meaning — it is literally the `REVISION_REQUIRED` bucket). The
     * missing-outcome figure reuses the frozen H-5 incomplete query verbatim
     * via its `total()`.
     *
     * @return array<string, mixed>
     */
    private function recruiterSnapshot(User $actor, ListIncompleteRecruitmentOutcomes $incompleteOutcomes): array
    {
        /** @var array<string, int> $byStatus */
        $byStatus = VacancyScope::queryFor($actor)
            ->select('current_status', DB::raw('count(*) as aggregate'))
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        $company = $this->membershipCompany($actor);

        return [
            'company' => $company === null ? null : [
                'name' => $company->name,
                'verification_status' => $company->verification_status instanceof CompanyStatus
                    ? $company->verification_status->value
                    : $company->verification_status,
            ],
            'counts' => [
                'applicants' => RecruiterApplicationScope::queryFor($actor)->count(),
                'schedules_upcoming' => SelectionScheduleScope::operationalQueryFor($actor)
                    ->where('status', 'SCHEDULED')->where('starts_at', '>=', now())->count(),
                'revision_requests' => $byStatus['REVISION_REQUIRED'] ?? 0,
                'incomplete_outcomes' => $incompleteOutcomes
                    ->execute(RecruitmentOutcomeScope::incompleteQueryFor($actor))->total(),
            ],
            'vacancies_by_status' => $byStatus,
        ];
    }

    /**
     * The actor's OWN company — the one they hold an ACTIVE, non-revoked
     * membership of (OL-1). Never the global-reader `CompanyScope`. A pure
     * SUPER_ADMIN with no membership gets `null` and the card is omitted.
     */
    private function membershipCompany(User $actor): ?object
    {
        return DB::table('companies')
            ->join('company_members', 'company_members.company_id', '=', 'companies.id')
            ->where('company_members.user_id', $actor->getKey())
            ->where('company_members.status', 'ACTIVE')
            ->whereNull('company_members.revoked_at')
            ->orderBy('companies.id')
            ->first(['companies.name', 'companies.verification_status']);
    }
}
