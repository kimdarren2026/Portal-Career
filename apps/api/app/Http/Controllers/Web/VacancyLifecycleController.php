<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Domains\Vacancy\Actions\ModerateVacancy;
use App\Domains\Vacancy\Actions\SubmitVacancyForReview;
use App\Domains\Vacancy\Enums\VacancyModerationAction;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyPresenter;
use App\Domains\Vacancy\Support\VacancyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacancy\ModerateVacancyRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Company vacancy submit, moderation and manual close.
 *
 * There is deliberately NO publish endpoint here (B-4): a company vacancy
 * publishes only through approval inside its active window or through the
 * scheduler, so no user of any role can publish one directly.
 *
 * Every operation is `Idempotency: REQUIRED` in the contract, so each runs
 * through the shared IdempotencyGuard: a replay with the same payload returns
 * the retained response and re-executes nothing.
 */
final class VacancyLifecycleController extends Controller
{
    public function __construct(private readonly IdempotencyGuard $idempotency) {}

    public function submit(ModerateVacancyRequest $request, int $vacancy, SubmitVacancyForReview $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);

        if (Gate::forUser($actor)->denies('submitForReview', $model)) {
            return $this->forbidden($request);
        }

        return $this->idempotent($request, $actor, 'vacancy.submit-review', $vacancy, function () use ($actor, $model, $action): array {
            return VacancyPresenter::detail($action->execute($actor, $model));
        });
    }

    public function requestRevision(ModerateVacancyRequest $request, int $vacancy, ModerateVacancy $action): JsonResponse
    {
        return $this->moderate($request, $vacancy, $action, VacancyModerationAction::RequestRevision);
    }

    public function reject(ModerateVacancyRequest $request, int $vacancy, ModerateVacancy $action): JsonResponse
    {
        return $this->moderate($request, $vacancy, $action, VacancyModerationAction::Reject);
    }

    public function approve(ModerateVacancyRequest $request, int $vacancy, ModerateVacancy $action): JsonResponse
    {
        return $this->moderate($request, $vacancy, $action, VacancyModerationAction::Approve);
    }

    public function suspend(ModerateVacancyRequest $request, int $vacancy, ModerateVacancy $action): JsonResponse
    {
        return $this->moderate($request, $vacancy, $action, VacancyModerationAction::Suspend);
    }

    public function restore(ModerateVacancyRequest $request, int $vacancy, ModerateVacancy $action): JsonResponse
    {
        return $this->moderate($request, $vacancy, $action, VacancyModerationAction::Restore);
    }

    /** Close is the one action an owner may also perform (B-3). */
    public function close(ModerateVacancyRequest $request, int $vacancy, ModerateVacancy $action): JsonResponse
    {
        return $this->moderate($request, $vacancy, $action, VacancyModerationAction::Close, 'close');
    }

    private function moderate(
        ModerateVacancyRequest $request,
        int $vacancy,
        ModerateVacancy $action,
        VacancyModerationAction $moderationAction,
        string $ability = 'moderate',
    ): JsonResponse {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $vacancy);

        if (Gate::forUser($actor)->denies($ability, $model)) {
            return $this->forbidden($request);
        }

        $operation = 'vacancy.'.mb_strtolower($moderationAction->value);

        return $this->idempotent($request, $actor, $operation, $vacancy, function () use ($actor, $model, $action, $moderationAction, $request): array {
            return VacancyPresenter::detail(
                $action->execute($actor, $model, $moderationAction, $request->moderationDetails()),
            );
        });
    }

    /** @param callable(): array<string, mixed> $execute */
    private function idempotent($request, User $actor, string $operation, int $vacancy, callable $execute): JsonResponse
    {
        $begin = $this->idempotency->begin(
            $request->header('Idempotency-Key'),
            $actor,
            $operation,
            ['vacancy' => $vacancy, 'body' => $request->all()],
        );

        if ($begin['status'] === 'reused') {
            return ContractResponse::error($request, 'IDEMPOTENCY_KEY_REUSED', 409, 'Kunci idempotensi digunakan ulang dengan payload berbeda.');
        }
        if ($begin['status'] === 'in_progress') {
            return ContractResponse::error($request, 'IDEMPOTENT_REPLAY_IN_PROGRESS', 409, 'Permintaan sebelumnya dengan kunci ini masih diproses.');
        }
        if ($begin['status'] === 'replay') {
            return ContractResponse::success($request, $begin['response_body']['data'] ?? [], $begin['response_status'])
                ->header('Idempotency-Replayed', 'true');
        }

        $id = $begin['id'] ?? null;

        try {
            $data = $execute();
        } catch (\Throwable $exception) {
            $this->idempotency->fail($id);

            throw $exception;
        }

        $this->idempotency->complete($id, 200, ['data' => $data]);

        return ContractResponse::success($request, $data);
    }

    private function scoped(User $actor, int $vacancyId): Vacancy
    {
        return VacancyScope::findFor($actor, $vacancyId) ?? throw new VacancyNotFound();
    }

    private function actor($request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function forbidden($request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
