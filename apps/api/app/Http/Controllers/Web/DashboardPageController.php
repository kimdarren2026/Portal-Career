<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Outcome\Queries\ListIncompleteRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Support\RecruitmentOutcomeScope;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleScope;
use App\Domains\Vacancy\Support\VacancyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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

    public function index(Request $request, ListIncompleteRecruitmentOutcomes $incompleteOutcomes): Response
    {
        $actor = $this->actor($request);

        if (RecruiterApplicationScope::isRecruiterOrAdmin($actor) || RecruiterApplicationScope::isSuperAdmin($actor)) {
            return Inertia::render('recruiter/Dashboard', $this->recruiterSnapshot($actor, $incompleteOutcomes));
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
