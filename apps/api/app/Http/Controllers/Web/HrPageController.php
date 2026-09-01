<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Queries\GetCompanyApplication;
use App\Domains\Application\Queries\ListCompanyApplications;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Identity\Actions\GetCurrentUserContext;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\MasterData\Queries\GetPublicReferenceData;
use App\Domains\Notification\Queries\ListNotifications;
use App\Domains\Notification\Support\NotificationPresenter;
use App\Domains\Notification\Support\NotificationScope;
use App\Domains\Recruitment\Evaluation\Presenters\EvaluationPresenter;
use App\Domains\Recruitment\Evaluation\Support\EvaluationScope;
use App\Domains\Recruitment\Offering\Presenters\OfferPresenter;
use App\Domains\Recruitment\Offering\Support\OfferScope;
use App\Domains\Recruitment\Outcome\Presenters\RecruitmentOutcomePresenter;
use App\Domains\Recruitment\Outcome\Queries\ListIncompleteRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Queries\ListRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Support\RecruitmentOutcomeScope;
use App\Domains\Recruitment\SelectionSchedule\Presenters\SelectionSchedulePresenter;
use App\Domains\Recruitment\SelectionSchedule\Queries\ListSelectionSchedules;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleScope;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Queries\GetVacancy;
use App\Domains\Vacancy\Queries\ListVacancies;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the Admin Kepegawaian (Campus Recruitment
 * Frontend v8) workspace under `/kepegawaian/*`. Every read reuses the frozen
 * Query/Scope/Presenter classes directly — all of which now carry the
 * activated `CAMPUS_SCOPE` branch (SPEC-DOC-10) — so an `HR_ADMIN` actor sees
 * only `ownership_type = CAMPUS` data and never a company vacancy or its
 * applicants. Mutations post to the frozen JSON routes (`/hr/vacancies*`,
 * `/applications/*`, `/schedules/*`, `/evaluations/*`, `/offers/*`,
 * `/recruitment-outcomes`, `/notifications/*`).
 *
 * "Laporan" is deliberately absent — FR-REP-003 / `GET /reports` has no
 * runtime; the nav item renders "Segera hadir".
 */
final class HrPageController extends Controller
{
    /** `GET /kepegawaian/dashboard` — FR-REP-003 operational snapshot (literal counts only). */
    public function dashboard(Request $request, ListIncompleteRecruitmentOutcomes $incomplete): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $byStatus = VacancyScope::queryFor($actor)
            ->select('current_status', DB::raw('count(*) as aggregate'))
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status')
            ->map(static fn ($c): int => (int) $c)->all();

        return Inertia::render('admin-kepegawaian/Dashboard', [
            'counts' => [
                'applicants' => RecruiterApplicationScope::queryFor($actor)->count(),
                'schedules_upcoming' => SelectionScheduleScope::operationalQueryFor($actor)
                    ->where('status', 'SCHEDULED')->where('starts_at', '>=', now())->count(),
                'offers_active' => OfferScope::operationalQueryFor($actor)->count(),
                'incomplete_outcomes' => $incomplete
                    ->execute(RecruitmentOutcomeScope::incompleteQueryFor($actor))->total(),
            ],
            'vacancies_by_status' => $byStatus,
        ]);
    }

    /** `GET /kepegawaian/lowongan-kampus`. */
    public function vacancyIndex(Request $request, ListVacancies $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $filters = array_intersect_key($request->query(), array_flip(['status', 'q']));
        $page = $query->execute(
            $actor,
            $filters,
            $request->string('sort', 'created_at')->toString(),
            $request->string('direction', 'desc')->toString(),
        );

        return Inertia::render('admin-kepegawaian/LowonganKampus', [
            'items' => $page->items(),
            'pagination' => self::pagination($page),
            'filters' => (object) $filters,
        ]);
    }

    /** `GET /kepegawaian/lowongan-kampus/baru`. */
    public function vacancyCreate(Request $request, GetPublicReferenceData $referenceData): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        return Inertia::render('admin-kepegawaian/LowonganKampusForm', [
            'mode' => 'create',
            'vacancy' => null,
            'reference_data' => $referenceData->execute(),
            'editable' => true,
            'lifecycle' => [],
        ]);
    }

    /** `GET /kepegawaian/lowongan-kampus/{vacancy}`. */
    public function vacancyShow(Request $request, int $vacancy, GetVacancy $query, GetPublicReferenceData $referenceData): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $model = VacancyScope::findFor($actor, $vacancy) ?? throw new VacancyNotFound();
        $detail = $query->execute($actor, $model, ['requirements', 'screening_questions', 'versions']);
        $status = $model->current_status;

        return Inertia::render('admin-kepegawaian/LowonganKampusForm', [
            'mode' => 'edit',
            'vacancy' => $detail,
            'reference_data' => $referenceData->execute(),
            'editable' => $status === VacancyStatus::Draft,
            'lifecycle' => self::lifecycleActions($status),
        ]);
    }

    /** `GET /kepegawaian/pelamar`. */
    public function applicantIndex(Request $request, ListCompanyApplications $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
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

        return Inertia::render('admin-kepegawaian/Pelamar', [
            'items' => $items,
            'pagination' => self::pagination($applications),
            'filters' => (object) $filters,
            'vacancies' => VacancyScope::queryFor($actor)->orderBy('title')->get(['id', 'title'])
                ->map(fn ($v): array => ['id' => (int) $v->id, 'title' => $v->title])->values()->all(),
        ]);
    }

    /** `GET /kepegawaian/pelamar/{application}`. */
    public function applicantShow(Request $request, int $application, GetCompanyApplication $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();
        $detail = $query->execute($model);
        $detail['vacancy_title'] = DB::table('vacancies')->where('id', $model->vacancy_id)->value('title');

        $stages = DB::table('recruitment_stages')->where('vacancy_id', $model->vacancy_id)
            ->where('active', true)->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'sort_order', 'candidate_visible_label'])->map(fn ($r): array => (array) $r)->values()->all();

        $schedules = SelectionScheduleScope::operationalQueryFor($actor)
            ->where('application_id', $model->getKey())->orderBy('starts_at', 'desc')->get()
            ->map(SelectionSchedulePresenter::operational(...))->values()->all();

        $evaluations = EvaluationScope::operationalQueryFor($actor)
            ->where('application_id', $model->getKey())->with('items')->orderBy('created_at')->orderBy('id')->get()
            ->map(EvaluationPresenter::summary(...))->values()->all();

        $offers = OfferScope::operationalQueryFor($actor)
            ->where('application_id', $model->getKey())->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get()
            ->map(OfferPresenter::operational(...))->values()->all();

        return Inertia::render('admin-kepegawaian/PelamarDetail', [
            'application' => $detail,
            'stages' => $stages,
            'schedules' => $schedules,
            'evaluations' => $evaluations,
            'offers' => $offers,
        ]);
    }

    /** `GET /kepegawaian/jadwal-seleksi`. */
    public function scheduleIndex(Request $request, ListSelectionSchedules $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $filters = array_intersect_key($request->query(), array_flip(ListSelectionSchedules::FILTERS));
        $schedules = $query->execute(
            SelectionScheduleScope::operationalQueryFor($actor),
            $filters,
            $request->string('sort', 'starts_at')->toString(),
            $request->string('direction', 'asc')->toString(),
        );

        return Inertia::render('admin-kepegawaian/JadwalSeleksi', [
            'items' => collect($schedules->items())->map(SelectionSchedulePresenter::operational(...))->values()->all(),
            'pagination' => self::pagination($schedules),
        ]);
    }

    /** `GET /kepegawaian/penilaian` — cross-campus evaluation list (read only; author from Pelamar). */
    public function evaluationIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $page = EvaluationScope::operationalQueryFor($actor)->with('items')
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate(20);
        $items = collect($page->items())->map(EvaluationPresenter::summary(...))->values()->all();
        $context = self::applicationContext(array_map(static fn ($m) => (int) $m->application_id, $page->items()));

        return Inertia::render('admin-kepegawaian/Penilaian', [
            'items' => array_map(static fn (array $row): array => $row + ($context[(int) ($row['application_id'] ?? 0)] ?? []), $items),
            'pagination' => self::pagination($page),
        ]);
    }

    /** `GET /kepegawaian/offering` — cross-campus offer list (read only; author from Pelamar). */
    public function offerIndex(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $page = OfferScope::operationalQueryFor($actor)
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate(20);
        $items = collect($page->items())->map(OfferPresenter::operational(...))->values()->all();
        $context = self::applicationContext(array_map(static fn ($m) => (int) $m->application_id, $page->items()));

        return Inertia::render('admin-kepegawaian/Offering', [
            'items' => array_map(static fn (array $row): array => $row + ($context[(int) ($row['application_id'] ?? 0)] ?? []), $items),
            'pagination' => self::pagination($page),
        ]);
    }

    /** `GET /kepegawaian/outcome-rekrutmen`. */
    public function outcomeIndex(Request $request, ListRecruitmentOutcomes $recorded, ListIncompleteRecruitmentOutcomes $incomplete): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $filters = array_intersect_key($request->query(), array_flip(ListRecruitmentOutcomes::FILTERS));
        $recordedPage = $recorded->execute(RecruitmentOutcomeScope::operationalQueryFor($actor), $filters);
        $incompletePage = $incomplete->execute(RecruitmentOutcomeScope::incompleteQueryFor($actor));

        return Inertia::render('admin-kepegawaian/OutcomeRekrutmen', [
            'recorded' => [
                'items' => collect($recordedPage->items())->map(RecruitmentOutcomePresenter::operational(...))->values()->all(),
                'pagination' => self::pagination($recordedPage),
            ],
            'incomplete' => [
                'items' => collect($incompletePage->items())->map(RecruitmentOutcomePresenter::incomplete(...))->values()->all(),
                'pagination' => self::pagination($incompletePage),
            ],
            'filters' => (object) $filters,
        ]);
    }

    /** `GET /kepegawaian/notifikasi` — the frozen OWN inbox, admin shell. */
    public function notifications(Request $request, ListNotifications $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $filters = array_intersect_key($request->query(), array_flip(ListNotifications::FILTERS));
        $page = $query->execute(NotificationScope::queryFor($actor), $filters);

        return Inertia::render('admin-kepegawaian/Notifikasi', [
            'items' => collect($page->items())->map(NotificationPresenter::summary(...))
                ->map(fn (array $row): array => $row + ['link' => self::notificationLink($row)])->values()->all(),
            'pagination' => self::pagination($page),
            'filters' => (object) $filters,
            'unread_count' => NotificationScope::unreadCount($actor),
        ]);
    }

    /** `GET /kepegawaian/pengaturan` — the frozen self-account context, admin shell. */
    public function settings(Request $request, GetCurrentUserContext $context): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->guard($request);
        if (! $actor instanceof User) {
            return $actor;
        }

        $me = $context->execute($actor);

        return Inertia::render('admin-kepegawaian/Pengaturan', [
            'account' => [
                'name' => $me['user']['name'],
                'email' => $me['user']['email'],
                'email_verified_at' => $me['user']['email_verified_at'],
                'status' => $me['user']['status'],
            ],
            'roles' => $me['roles'],
            'company_memberships' => [],
        ]);
    }

    // ------------------------------------------------------------------

    /** @return list<string> */
    private static function lifecycleActions(?VacancyStatus $status): array
    {
        return match ($status) {
            VacancyStatus::Draft => ['publish', 'schedule'],
            VacancyStatus::Scheduled => ['publish'],
            VacancyStatus::Published => ['close', 'suspend'],
            VacancyStatus::Suspended => ['restore'],
            default => [],
        };
    }

    /** @param array<string, mixed> $row */
    private static function notificationLink(array $row): ?string
    {
        $id = $row['related_object_id'] ?? null;

        return match ($row['related_object_type'] ?? null) {
            'vacancy' => $id === null ? null : "/kepegawaian/lowongan-kampus/{$id}",
            'application' => $id === null ? null : "/kepegawaian/pelamar/{$id}",
            'selection_schedule' => '/kepegawaian/jadwal-seleksi',
            default => null,
        };
    }

    /** @param list<int> $applicationIds @return array<int, array{application_code: ?string, vacancy_title: ?string}> */
    private static function applicationContext(array $applicationIds): array
    {
        $ids = array_values(array_unique(array_filter($applicationIds)));
        if ($ids === []) {
            return [];
        }
        $apps = DB::table('applications')->whereIn('id', $ids)->pluck('vacancy_id', 'id');
        $codes = DB::table('applications')->whereIn('id', $ids)->pluck('application_code', 'id');
        $titles = DB::table('vacancies')->whereIn('id', $apps->values()->all())->pluck('title', 'id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [
                'application_code' => $codes[$id] ?? null,
                'vacancy_title' => isset($apps[$id]) ? ($titles[$apps[$id]] ?? null) : null,
            ];
        }

        return $out;
    }

    /** @param list<int|null> $vacancyIds @return array<int, string> */
    private static function vacancyTitles(array $vacancyIds): array
    {
        $ids = array_values(array_unique(array_filter($vacancyIds, static fn ($id): bool => $id !== null)));

        return $ids === [] ? [] : DB::table('vacancies')->whereIn('id', $ids)->pluck('title', 'id')->all();
    }

    /** @return array<string, mixed> */
    private static function pagination(LengthAwarePaginator $p): array
    {
        return ['page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()];
    }

    /** Returns the actor when authorized, or a 403 response otherwise. */
    private function guard(Request $request): User|Response|JsonResponse|SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->hasActiveRole(RoleCode::HrAdmin) || $user->hasActiveRole(RoleCode::SuperAdmin)) {
            return $user;
        }

        if ($request->expectsJson()) {
            return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
        }

        return Inertia::render('Error', ['status' => 403])->toResponse($request)->setStatusCode(403);
    }
}
