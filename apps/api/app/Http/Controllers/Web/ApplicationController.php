<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Actions\SubmitApplication;
use App\Domains\Application\Actions\WithdrawApplication;
use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Queries\GetCandidateApplication;
use App\Domains\Application\Queries\ListCandidateApplications;
use App\Domains\Application\Support\ApplicationPresenter;
use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Requests\Application\ListApplicationsRequest;
use App\Http\Requests\Application\SubmitApplicationRequest;
use App\Http\Requests\Application\WithdrawApplicationRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Candidate Application Foundation v1.
 *
 * `reopen` is deliberately absent: AD-2 remains OPEN and deferred — no route,
 * no Action, no `APPLICATION_REOPENED` code path exists anywhere in this
 * controller. Recruiter/owner applicant management (transition, move-stage,
 * bulk-transition, COMPANY_SCOPE/CAMPUS_SCOPE listing) belongs to a later
 * phase and is not implemented here.
 */
final class ApplicationController extends CandidateController
{
    public function __construct(
        CandidateProfileResolver $profiles,
        private readonly ApplicationScope $scope,
        private readonly IdempotencyGuard $idempotency,
    ) {
        parent::__construct($profiles);
    }

    public function store(SubmitApplicationRequest $request, int $vacancy, SubmitApplication $action): JsonResponse
    {
        $actor = $this->actor($request);
        $profile = $this->ownProfile($request);

        return $this->idempotent($request, $actor, 'application.submit', $vacancy, function () use ($actor, $profile, $vacancy, $request, $action): array {
            // ApplicationAlreadyExists (carrying the existing application id)
            // is handled by the global exception mapping — never caught here,
            // so the response shape cannot drift by route.
            $application = $action->execute($actor, $profile, $vacancy, $request->validated());

            return ApplicationPresenter::summary($application);
        }, 201);
    }

    public function index(ListApplicationsRequest $request, ListCandidateApplications $query): JsonResponse
    {
        $actor = $this->actor($request);

        $applications = $query->execute(
            $actor,
            array_intersect_key($request->query(), array_flip(ListCandidateApplications::FILTERS)),
            $request->string('sort', 'first_applied_at')->toString(),
            $request->string('direction', 'desc')->toString(),
        );

        return ContractResponse::success($request, [
            'items' => $applications->items(),
            'pagination' => [
                'page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $application, GetCandidateApplication $query): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $application);

        $requested = array_filter(array_map('trim', explode(',', $request->string('include')->toString())));
        $include = array_values(array_intersect($requested, GetCandidateApplication::INCLUDES));

        return ContractResponse::success($request, $query->execute($model, $include));
    }

    public function withdraw(WithdrawApplicationRequest $request, int $application, WithdrawApplication $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->scoped($actor, $application);

        return $this->idempotent($request, $actor, 'application.withdraw', $application, function () use ($actor, $model, $request, $action): array {
            $withdrawn = $action->execute($actor, $model, $request->filled('reason') ? $request->string('reason')->toString() : null);

            return ApplicationPresenter::summary($withdrawn);
        });
    }

    private function scoped(User $actor, int $applicationId): Application
    {
        return $this->scope->findFor($actor, $applicationId) ?? throw new ApplicationNotFound();
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
