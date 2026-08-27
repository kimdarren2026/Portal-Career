<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /dashboard` — minimal landing page per persona (Frontend Vertical
 * Slice v1). Counts are read-only aggregates over the same scoped queries
 * already frozen elsewhere; no new business aggregate/metric is invented.
 */
final class DashboardPageController extends CandidateController
{
    public function __construct(CandidateProfileResolver $profiles) { parent::__construct($profiles); }

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);

        if (RecruiterApplicationScope::isRecruiterOrAdmin($actor) || RecruiterApplicationScope::isSuperAdmin($actor)) {
            return Inertia::render('recruiter/Dashboard', [
                'counts' => [
                    'applicants' => RecruiterApplicationScope::queryFor($actor)->count(),
                    'schedules_upcoming' => SelectionScheduleScope::operationalQueryFor($actor)
                        ->where('status', 'SCHEDULED')->where('starts_at', '>=', now())->count(),
                ],
            ]);
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
}
