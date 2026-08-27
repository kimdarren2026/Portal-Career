<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\SelectionScheduleNotFound;
use App\Domains\Recruitment\SelectionSchedule\Presenters\SelectionSchedulePresenter;
use App\Domains\Recruitment\SelectionSchedule\Queries\ListSelectionSchedules;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Non-mutating Inertia delivery for `GET /jadwal-seleksi` (Frontend Vertical
 * Slice v1) — one canonical nav destination for both personas, branching by
 * actor capability exactly like the JSON `SelectionScheduleController::index`
 * already does. Reuses `SelectionScheduleScope`/`ListSelectionSchedules`
 * directly, never a loopback HTTP call. Mutations (create, reschedule,
 * cancel) still post to the existing `/schedules` and
 * `/applications/{application}/schedules` JSON routes from the client.
 */
final class SelectionSchedulePageController extends CandidateController
{
    /** `GET /jadwal-seleksi`. */
    public function index(Request $request, ListSelectionSchedules $query): Response
    {
        $actor = $this->actor($request);
        $filters = array_intersect_key($request->query(), array_flip(ListSelectionSchedules::FILTERS));
        $sort = $request->string('sort', 'starts_at')->toString();
        $direction = $request->string('direction', 'asc')->toString();

        if ($this->isOperationalActor($actor)) {
            $schedules = $query->execute(SelectionScheduleScope::operationalQueryFor($actor), $filters, $sort, $direction);

            return Inertia::render('recruiter/JadwalSeleksi', [
                'items' => collect($schedules->items())->map(SelectionSchedulePresenter::operational(...))->values()->all(),
                'pagination' => self::pagination($schedules),
            ]);
        }

        $profile = $this->ownProfile($request);
        $schedules = $query->execute(SelectionScheduleScope::candidateQueryFor($profile), $filters, $sort, $direction);

        return Inertia::render('candidate/JadwalSeleksi', [
            'items' => collect($schedules->items())->map(SelectionSchedulePresenter::candidate(...))->values()->all(),
            'pagination' => self::pagination($schedules),
        ]);
    }

    /** `GET /jadwal-seleksi/{schedule}`. */
    public function show(Request $request, int $schedule): Response
    {
        $actor = $this->actor($request);

        if ($this->isOperationalActor($actor)) {
            $model = SelectionScheduleScope::findFor($actor, $schedule) ?? throw new SelectionScheduleNotFound();
            $events = $model->histories()->orderBy('occurred_at')->orderBy('id')->get()
                ->map(SelectionSchedulePresenter::historyOperational(...))->values()->all();

            return Inertia::render('recruiter/JadwalDetail', [
                'schedule' => SelectionSchedulePresenter::operational($model),
                'history' => $events,
                'application_id' => (int) $model->application_id,
            ]);
        }

        $profile = $this->ownProfile($request);
        $model = SelectionScheduleScope::candidateFindFor($profile, $schedule) ?? throw new SelectionScheduleNotFound();
        $events = $model->histories()->orderBy('occurred_at')->orderBy('id')->get()
            ->map(SelectionSchedulePresenter::historyCandidate(...))->values()->all();

        return Inertia::render('candidate/JadwalDetail', [
            'schedule' => SelectionSchedulePresenter::candidate($model),
            'history' => $events,
        ]);
    }

    /** Read-eligible non-candidate actors: recruiter/admin, Super Admin, and Auditor (READ_ONLY). */
    private function isOperationalActor(User $actor): bool
    {
        return SelectionScheduleScope::isRecruiterOrAdmin($actor)
            || SelectionScheduleScope::isSuperAdmin($actor)
            || SelectionScheduleScope::isAuditor($actor);
    }

    /** @return array<string, mixed> */
    private static function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
