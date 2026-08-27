<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Queries\GetCompanyApplication;
use App\Domains\Application\Queries\ListCompanyApplications;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\SelectionSchedule\Presenters\SelectionSchedulePresenter;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleScope;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Non-mutating Inertia delivery for the recruiter applicant workspace
 * (Frontend Vertical Slice v1). Reuses the frozen Recruiter Applicant
 * Management queries/scopes directly, never a loopback HTTP call. Mutations
 * (transition, move-stage) still post to the existing `ApplicationController`
 * JSON routes from the client. Selector assignment, evaluation, offering,
 * and outcome UI are explicitly out of this slice.
 */
final class RecruiterApplicantPageController extends Controller
{
    /** `GET /pelamar`. */
    public function index(Request $request, ListCompanyApplications $query): Response|JsonResponse
    {
        $actor = $this->actor($request);
        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->query(), array_flip(ListCompanyApplications::FILTERS));
        $applications = $query->execute(
            $actor,
            $filters,
            $request->string('sort', 'first_applied_at')->toString(),
            $request->string('direction', 'desc')->toString(),
        );

        $items = $applications->items();
        $vacancyTitles = self::vacancyTitles(array_column($items, 'vacancy_id'));
        foreach ($items as &$item) {
            $item['vacancy_title'] = $vacancyTitles[$item['vacancy_id']] ?? null;
        }
        unset($item);

        return Inertia::render('recruiter/Pelamar', [
            'items' => $items,
            'pagination' => [
                'page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
            'filters' => (object) $filters,
            'vacancies' => self::ownedVacancyOptions($actor),
        ]);
    }

    /** `GET /pelamar/{application}`. */
    public function show(Request $request, int $application, GetCompanyApplication $query): Response|JsonResponse
    {
        $actor = $this->actor($request);
        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();
        $detail = $query->execute($model);
        $detail['vacancy_title'] = DB::table('vacancies')->where('id', $model->vacancy_id)->value('title');

        $stages = DB::table('recruitment_stages')->where('vacancy_id', $model->vacancy_id)
            ->where('active', true)->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'sort_order', 'candidate_visible_label'])->map(fn ($row): array => (array) $row)->values()->all();

        $schedules = SelectionScheduleScope::operationalQueryFor($actor)
            ->where('application_id', $model->getKey())
            ->orderBy('starts_at', 'desc')->get()
            ->map(SelectionSchedulePresenter::operational(...))->values()->all();

        return Inertia::render('recruiter/ApplicantDetail', [
            'application' => $detail,
            'stages' => $stages,
            'schedules' => $schedules,
        ]);
    }

    private function isRecruiterActor(User $actor): bool
    {
        return RecruiterApplicationScope::isRecruiterOrAdmin($actor) || RecruiterApplicationScope::isSuperAdmin($actor);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
    }

    /** @param list<int|null> $vacancyIds @return array<int, string> */
    private static function vacancyTitles(array $vacancyIds): array
    {
        $ids = array_values(array_unique(array_filter($vacancyIds, fn ($id): bool => $id !== null)));
        if ($ids === []) {
            return [];
        }

        return DB::table('vacancies')->whereIn('id', $ids)->pluck('title', 'id')->all();
    }

    /** Vacancy filter options for the Pelamar list — id/title only, from the actor's own scoped vacancies. @return list<array{id: int, title: string}> */
    private static function ownedVacancyOptions(User $actor): array
    {
        return VacancyScope::queryFor($actor)->orderBy('title')->get(['id', 'title'])
            ->map(fn ($vacancy): array => ['id' => (int) $vacancy->id, 'title' => $vacancy->title])->values()->all();
    }
}
