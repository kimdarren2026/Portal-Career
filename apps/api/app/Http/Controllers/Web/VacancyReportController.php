<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Support\PublicVacancyPresenter;
use App\Domains\Vacancy\Support\PublicVacancyScope;
use App\Domains\VacancyReport\Actions\SubmitVacancyReport;
use App\Domains\VacancyReport\Actions\TransitionVacancyReport;
use App\Domains\VacancyReport\Queries\ListVacancyReports;
use App\Domains\VacancyReport\Support\VacancyReportVocabulary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SubmitVacancyReportRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * "Laporkan Lowongan" (PGC-V1 / PD-C).
 *
 *  - `create` / `store` — public (anonymous OR authenticated). `store` is
 *    rate-limited (5/IP/hour anonymous, 10/account/day authenticated).
 *  - `index` / `startReview` / `resolve` — Career Center review queue and
 *    transitions. `CAREER_CENTER_STAFF` / `CAREER_CENTER_MANAGER` transition;
 *    `SUPER_ADMIN` may read only.
 */
final class VacancyReportController extends Controller
{
    /** `GET /lowongan/{vacancy}/laporkan` — the public report form page. */
    public function create(Request $request, string $vacancy): Response|SymfonyResponse
    {
        $model = PublicVacancyScope::query()->with('company')->where('slug', $vacancy)->first();
        if ($model === null) {
            return Inertia::render('Error', ['status' => 404])->toResponse($request)->setStatusCode(404);
        }

        return Inertia::render('public/ReportVacancy', [
            'vacancy' => [
                'slug' => $model->slug,
                'title' => $model->title,
                'company' => PublicVacancyPresenter::companySummary($model)['name'] ?? null,
            ],
            'reasons' => VacancyReportVocabulary::reasonOptions(),
            'authenticated' => $request->user() !== null,
        ]);
    }

    /** `POST /lowongan/{vacancy}/laporkan`. */
    public function store(SubmitVacancyReportRequest $request, string $vacancy, SubmitVacancyReport $action): JsonResponse
    {
        /** @var User|null $reporter */
        $reporter = $request->user();

        $report = $action->execute($reporter, $vacancy, $request->validated());

        return ContractResponse::success($request, [
            'id' => (int) $report->getKey(),
            'status' => $report->status,
        ], 201);
    }

    /** `GET /moderasi-lowongan/laporan` — Career Center review queue (JSON). */
    public function index(Request $request, ListVacancyReports $query): JsonResponse
    {
        if (! $this->mayRead($request->user())) {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->query(), array_flip(ListVacancyReports::FILTERS));
        $page = $query->execute($filters);

        return ContractResponse::success($request, [
            'items' => $page->items(),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /** `POST /vacancy-reports/{report}/review` — NEW -> UNDER_REVIEW. */
    public function startReview(Request $request, int $report, TransitionVacancyReport $action): JsonResponse
    {
        $actor = $this->careerCenterActorOrNull($request);
        if ($actor === null) {
            return $this->forbidden($request);
        }

        $updated = $action->start($actor, $report);

        return ContractResponse::success($request, ['id' => (int) $updated->getKey(), 'status' => $updated->status]);
    }

    /** `POST /vacancy-reports/{report}/{outcome}` where outcome is `action` or `dismiss`. */
    public function resolve(Request $request, int $report, string $outcome, TransitionVacancyReport $action): JsonResponse
    {
        $actor = $this->careerCenterActorOrNull($request);
        if ($actor === null) {
            return $this->forbidden($request);
        }

        if (! in_array($outcome, ['action', 'dismiss'], true)) {
            return ContractResponse::error($request, 'NOT_FOUND', 404, 'Aksi tidak dikenali.');
        }

        $note = $request->filled('note') ? (string) $request->input('note') : null;
        $updated = $outcome === 'action'
            ? $action->action($actor, $report, $note)
            : $action->dismiss($actor, $report, $note);

        return ContractResponse::success($request, ['id' => (int) $updated->getKey(), 'status' => $updated->status]);
    }

    private function mayRead(?User $user): bool
    {
        return $user !== null && (
            $user->hasActiveRole(RoleCode::CareerCenterStaff)
            || $user->hasActiveRole(RoleCode::CareerCenterManager)
            || $user->hasActiveRole(RoleCode::SuperAdmin)
        );
    }

    /** Career Center staff/manager only — the business authority for transitions (Super Admin is read-only here). */
    private function careerCenterActorOrNull(Request $request): ?User
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user !== null && (
            $user->hasActiveRole(RoleCode::CareerCenterStaff)
            || $user->hasActiveRole(RoleCode::CareerCenterManager)
        )) {
            return $user;
        }

        return null;
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
