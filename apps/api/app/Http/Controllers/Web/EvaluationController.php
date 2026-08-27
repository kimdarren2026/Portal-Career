<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Evaluation\Actions\CreateEvaluation;
use App\Domains\Recruitment\Evaluation\Actions\SubmitEvaluation;
use App\Domains\Recruitment\Evaluation\Actions\UpdateEvaluation;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationNotFound;
use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use App\Domains\Recruitment\Evaluation\Presenters\EvaluationPresenter;
use App\Domains\Recruitment\Evaluation\Support\EvaluationScope;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\CreateEvaluationRequest;
use App\Http\Requests\Evaluation\UpdateEvaluationRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Evaluation / Scoring Foundation v1 (EV-1, EV-2, RC-1, all approved and
 * CLOSED). `COMPANY` vacancies only — `CAMPUS_SCOPE` is not activated, no
 * Campus vacancy runtime exists. Evaluations are never candidate-visible
 * (FR-HR-007) — no candidate-facing code path exists anywhere in this
 * controller. Item-set mutation via `PATCH`, company-side Selector
 * Assignment, and every other deferred capability remain unimplemented.
 */
final class EvaluationController extends Controller
{
    public function __construct(private readonly IdempotencyGuard $idempotency) {}

    public function store(CreateEvaluationRequest $request, int $application, CreateEvaluation $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();

        $evaluation = $action->execute($actor, $model, $request->validated());

        return ContractResponse::success($request, EvaluationPresenter::summary($evaluation), 201);
    }

    public function index(Request $request, int $application): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();

        $items = Evaluation::query()->where('application_id', $model->getKey())
            ->with('items')->orderBy('created_at')->orderBy('id')
            ->get()->map(EvaluationPresenter::summary(...))->values()->all();

        return ContractResponse::success($request, ['items' => $items]);
    }

    public function show(Request $request, int $evaluation): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = EvaluationScope::findFor($actor, $evaluation) ?? throw new EvaluationNotFound();
        $model->load('items');

        return ContractResponse::success($request, EvaluationPresenter::summary($model));
    }

    public function update(UpdateEvaluationRequest $request, int $evaluation, UpdateEvaluation $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = EvaluationScope::findFor($actor, $evaluation) ?? throw new EvaluationNotFound();

        $updated = $action->execute($actor, $model, $request->validated());

        return ContractResponse::success($request, EvaluationPresenter::summary($updated));
    }

    public function submit(Request $request, int $evaluation, SubmitEvaluation $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = EvaluationScope::findFor($actor, $evaluation) ?? throw new EvaluationNotFound();

        return $this->idempotent($request, $actor, 'evaluation.submit', $evaluation, function () use ($actor, $model, $action): array {
            $submitted = $action->execute($actor, $model);

            return EvaluationPresenter::summary($submitted);
        });
    }

    private function isRecruiterActor(User $actor): bool
    {
        return EvaluationScope::isRecruiterOrAdmin($actor) || EvaluationScope::isSuperAdmin($actor);
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
