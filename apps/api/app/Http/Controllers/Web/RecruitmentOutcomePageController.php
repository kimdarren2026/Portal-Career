<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Outcome\Presenters\RecruitmentOutcomePresenter;
use App\Domains\Recruitment\Outcome\Queries\ListIncompleteRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Queries\ListRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Support\RecruitmentOutcomeScope;
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
 * Non-mutating Inertia delivery for `GET /outcome-rekrutmen` (Frontend
 * Vertical Slice v2) — activates the canonical recruiter nav item "Outcome
 * Rekrutmen". Reuses the frozen Recruitment Outcome queries/scope/presenter
 * directly (never a loopback HTTP call) and mutates nothing. Create and
 * correct still post/patch to the existing `/recruitment-outcomes` JSON
 * routes. Read authorization mirrors `RecruitmentOutcomeController::index`
 * exactly: recruiter/admin (`COMPANY_SCOPE`), Super Admin, Auditor
 * (READ_ONLY); Career Center and Candidate are DENY.
 */
final class RecruitmentOutcomePageController extends Controller
{
    /** `GET /outcome-rekrutmen`. */
    public function index(
        Request $request,
        ListRecruitmentOutcomes $recorded,
        ListIncompleteRecruitmentOutcomes $incomplete,
    ): Response|JsonResponse|SymfonyResponse {
        $actor = $this->actor($request);
        if (! $this->isReadActor($actor)) {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->query(), array_flip(ListRecruitmentOutcomes::FILTERS));

        $recordedPage = $recorded->execute(RecruitmentOutcomeScope::operationalQueryFor($actor), $filters);
        $incompletePage = $incomplete->execute(RecruitmentOutcomeScope::incompleteQueryFor($actor));

        return Inertia::render('recruiter/OutcomeRekrutmen', [
            'recorded' => self::page($recordedPage, RecruitmentOutcomePresenter::operational(...), self::titles($recordedPage)),
            'incomplete' => self::page($incompletePage, RecruitmentOutcomePresenter::incomplete(...), self::titles($incompletePage)),
            'filters' => (object) $filters,
            'can_write' => $this->isWriteActor($actor),
        ]);
    }

    /** List: recruiter/admin, Super Admin, Auditor (READ_ONLY). */
    private function isReadActor(User $actor): bool
    {
        return RecruitmentOutcomeScope::isRecruiterOrAdmin($actor)
            || RecruitmentOutcomeScope::isSuperAdmin($actor)
            || RecruitmentOutcomeScope::isAuditor($actor);
    }

    /** Create/correct: recruiter/admin (`COMPANY_SCOPE`) and Super Admin. Auditor is read-only. */
    private function isWriteActor(User $actor): bool
    {
        return RecruitmentOutcomeScope::isRecruiterOrAdmin($actor) || RecruitmentOutcomeScope::isSuperAdmin($actor);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * Browser/Inertia page navigation gets the shared styled `Error` page; a
     * request that explicitly wants JSON keeps the frozen `ContractResponse`
     * 403 envelope — the same split every other page controller uses.
     */
    private function forbidden(Request $request): Response|JsonResponse|SymfonyResponse
    {
        if ($request->expectsJson()) {
            return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
        }

        return Inertia::render('Error', ['status' => 403])->toResponse($request)->setStatusCode(403);
    }

    /**
     * @param callable(mixed): array<string, mixed> $present
     * @param array<int, array{application_code: string|null, vacancy_title: string|null}> $context
     * @return array<string, mixed>
     */
    private static function page(LengthAwarePaginator $paginator, callable $present, array $context): array
    {
        return [
            'items' => collect($paginator->items())->map($present)->map(static function (array $row) use ($context): array {
                $id = isset($row['application_id']) ? (int) $row['application_id'] : 0;

                return $row + ($context[$id] ?? ['application_code' => null, 'vacancy_title' => null]);
            })->values()->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Read-only display enrichment the recruiter is already authorized to see
     * on `/pelamar/{application}` — resolved by a single batched lookup, never
     * per row. Works for both list shapes: `RecruitmentOutcome` rows carry
     * `application_id`; `incomplete` rows are `Application` models keyed by id.
     *
     * @return array<int, array{application_code: string|null, vacancy_title: string|null}>
     */
    private static function titles(LengthAwarePaginator $paginator): array
    {
        $ids = collect($paginator->items())
            ->map(static fn ($model) => (int) ($model->application_id ?? $model->getKey()))
            ->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $applications = DB::table('applications')->whereIn('id', $ids)->pluck('vacancy_id', 'id');
        $codes = DB::table('applications')->whereIn('id', $ids)->pluck('application_code', 'id');
        $vacancyTitles = DB::table('vacancies')->whereIn('id', $applications->values()->all())->pluck('title', 'id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [
                'application_code' => $codes[$id] ?? null,
                'vacancy_title' => isset($applications[$id]) ? ($vacancyTitles[$applications[$id]] ?? null) : null,
            ];
        }

        return $out;
    }
}
