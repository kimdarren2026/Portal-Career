<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Outcome\Actions\CreateRecruitmentOutcome;
use App\Domains\Recruitment\Outcome\Actions\UpdateRecruitmentOutcome;
use App\Domains\Recruitment\Outcome\Exceptions\RecruitmentOutcomeNotFound;
use App\Domains\Recruitment\Outcome\Presenters\RecruitmentOutcomePresenter;
use App\Domains\Recruitment\Outcome\Queries\ListRecruitmentOutcomes;
use App\Domains\Recruitment\Outcome\Support\RecruitmentOutcomeScope;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Outcome\CreateRecruitmentOutcomeRequest;
use App\Http\Requests\Outcome\ListRecruitmentOutcomesRequest;
use App\Http\Requests\Outcome\UpdateRecruitmentOutcomeRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Recruitment Outcome Foundation v1 (OC-1, RC-2, both approved and CLOSED).
 * `INTERNAL_APPLICATION`/`COMPANY` only — `CAMPUS_SCOPE` and Career Center's
 * alumni/reporting grant (RC-2) remain deferred/inactive. No candidate
 * route exists. `GET /recruitment-outcomes/incomplete` is deliberately not
 * routed (H-5, `API_CONTRACT.md`).
 */
final class RecruitmentOutcomeController extends Controller
{
    public function __construct(private readonly IdempotencyGuard $idempotency) {}

    public function store(CreateRecruitmentOutcomeRequest $request, CreateRecruitmentOutcome $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isWriteActor($actor)) {
            return $this->forbidden($request);
        }

        $application = RecruiterApplicationScope::findFor($actor, (int) $request->input('application_id')) ?? throw new ApplicationNotFound();

        return $this->idempotent($request, $actor, 'recruitment_outcome.create', $application->getKey(), function () use ($actor, $application, $request, $action): array {
            $outcome = $action->execute($actor, $application, $request->validated());

            return RecruitmentOutcomePresenter::operational($outcome);
        }, 201);
    }

    public function index(ListRecruitmentOutcomesRequest $request, ListRecruitmentOutcomes $query): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isReadActor($actor)) {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->query(), array_flip(ListRecruitmentOutcomes::FILTERS));
        $outcomes = $query->execute(RecruitmentOutcomeScope::operationalQueryFor($actor), $filters);

        return ContractResponse::success($request, $this->paginated($outcomes, RecruitmentOutcomePresenter::operational(...)));
    }

    public function update(UpdateRecruitmentOutcomeRequest $request, int $outcome, UpdateRecruitmentOutcome $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isWriteActor($actor)) {
            return $this->forbidden($request);
        }

        $model = RecruitmentOutcomeScope::findFor($actor, $outcome) ?? throw new RecruitmentOutcomeNotFound();

        $updated = $action->execute($actor, $model, $request->validated());

        return ContractResponse::success($request, RecruitmentOutcomePresenter::operational($updated));
    }

    /** Create/correct: COMPANY_SCOPE (recruiter/admin) and SUPER_ADMIN. Auditor is read-only; Career Center is DENY (RC-2). */
    private function isWriteActor(User $actor): bool
    {
        return RecruitmentOutcomeScope::isRecruiterOrAdmin($actor) || RecruitmentOutcomeScope::isSuperAdmin($actor);
    }

    /** List: recruiter/admin, Super Admin, and Auditor (READ_ONLY). */
    private function isReadActor(User $actor): bool
    {
        return RecruitmentOutcomeScope::isRecruiterOrAdmin($actor)
            || RecruitmentOutcomeScope::isSuperAdmin($actor)
            || RecruitmentOutcomeScope::isAuditor($actor);
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

    /** @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator @return array<string, mixed> */
    private function paginated($paginator, callable $present): array
    {
        return [
            'items' => collect($paginator->items())->map($present)->values()->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /** @param callable(): array<string, mixed> $execute */
    private function idempotent(Request $request, User $actor, string $operation, int $subjectId, callable $execute, int $successStatus = 200): JsonResponse
    {
        $begin = $this->idempotency->begin(
            $request->header('Idempotency-Key'),
            $actor,
            $operation,
            ['subject' => $subjectId, 'body' => $request->all()],
        );

        if ($begin['status'] === 'reused') {
            return ContractResponse::error($request, 'IDEMPOTENCY_KEY_REUSED', 409, 'Kunci idempotensi digunakan ulang dengan payload berbeda.');
        }
        if ($begin['status'] === 'in_progress') {
            return ContractResponse::error($request, 'IDEMPOTENT_REPLAY_IN_PROGRESS', 409, 'Permintaan sebelumnya dengan kunci ini masih diproses.');
        }
        if ($begin['status'] === 'replay') {
            return ContractResponse::success($request, $begin['response_body']['data'] ?? [], $begin['response_status']);
        }

        $id = $begin['id'] ?? null;

        try {
            $data = $execute();
        } catch (Throwable $exception) {
            $this->idempotency->fail($id);

            throw $exception;
        }

        $this->idempotency->complete($id, $successStatus, ['data' => $data]);

        return ContractResponse::success($request, $data, $successStatus);
    }
}
