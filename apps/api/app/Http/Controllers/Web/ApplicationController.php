<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Actions\SubmitApplication;
use App\Domains\Application\Actions\TransitionApplication;
use App\Domains\Application\Actions\WithdrawApplication;
use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Queries\GetCandidateApplication;
use App\Domains\Application\Queries\GetCompanyApplication;
use App\Domains\Application\Queries\ListCandidateApplications;
use App\Domains\Application\Queries\ListCompanyApplications;
use App\Domains\Application\Support\ApplicationPresenter;
use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Application\Support\RecruiterApplicationPresenter;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Requests\Application\ListApplicationsRequest;
use App\Http\Requests\Application\SubmitApplicationRequest;
use App\Http\Requests\Application\TransitionApplicationRequest;
use App\Http\Requests\Application\WithdrawApplicationRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Candidate Application Foundation v1, extended by Recruiter Applicant
 * Management Foundation v1 (RA-1, RA-2, both approved and CLOSED).
 *
 * `index()`/`show()` serve the one frozen `GET /applications(/{application})`
 * operation for every actor type (AUTHORIZATION_MATRIX.md §4.6): a
 * `COMPANY_RECRUITER`/`COMPANY_ADMIN`/`SUPER_ADMIN` actor is routed to the
 * `COMPANY_SCOPE`/`ALLOW` recruiter path; every other actor falls through to
 * the original, unmodified candidate `OWN` path. `CAMPUS_SCOPE` and
 * `ASSIGNED_STAGE` are not implemented — no fallback exists for them.
 *
 * `reopen`, `move-stage`, `bulk-transition`, and document download remain
 * deliberately absent: AD-2 stays OPEN and deferred, and move-stage/bulk/
 * download are explicitly out of Recruiter Applicant Management Foundation
 * v1's scope (RA-3 defers download).
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

    public function index(
        ListApplicationsRequest $request,
        ListCandidateApplications $candidateQuery,
        ListCompanyApplications $companyQuery,
    ): JsonResponse {
        $actor = $this->actor($request);

        if ($this->isRecruiterActor($actor)) {
            $applications = $companyQuery->execute(
                $actor,
                array_intersect_key($request->query(), array_flip(ListCompanyApplications::FILTERS)),
                $request->string('sort', 'first_applied_at')->toString(),
                $request->string('direction', 'desc')->toString(),
            );
        } else {
            $applications = $candidateQuery->execute(
                $actor,
                array_intersect_key($request->query(), array_flip(ListCandidateApplications::FILTERS)),
                $request->string('sort', 'first_applied_at')->toString(),
                $request->string('direction', 'desc')->toString(),
            );
        }

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

    public function show(Request $request, int $application, GetCandidateApplication $candidateQuery, GetCompanyApplication $companyQuery): JsonResponse
    {
        $actor = $this->actor($request);

        if ($this->isRecruiterActor($actor)) {
            $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();

            return ContractResponse::success($request, $companyQuery->execute($model));
        }

        $model = $this->scoped($actor, $application);

        $requested = array_filter(array_map('trim', explode(',', $request->string('include')->toString())));
        $include = array_values(array_intersect($requested, GetCandidateApplication::INCLUDES));

        return ContractResponse::success($request, $candidateQuery->execute($model, $include));
    }

    public function transition(TransitionApplicationRequest $request, int $application, TransitionApplication $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
        }

        $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();

        return $this->idempotent($request, $actor, 'application.transition', $application, function () use ($actor, $model, $request, $action): array {
            $transitioned = $action->execute(
                $actor,
                $model,
                ApplicationStatus::from($request->string('to_status')->toString()),
                $request->string('candidate_visibility')->toString(),
                $request->filled('candidate_visible_note') ? $request->string('candidate_visible_note')->toString() : null,
                $request->filled('reason') ? $request->string('reason')->toString() : null,
                $this->expectedVersion($request),
            );

            return RecruiterApplicationPresenter::summary($transitioned);
        });
    }

    private function isRecruiterActor(User $actor): bool
    {
        return RecruiterApplicationScope::isRecruiterOrAdmin($actor) || RecruiterApplicationScope::isSuperAdmin($actor);
    }

    /** `If-Match` carries the history-row count the client last read (PATCH concurrency rule, mirrors VacancyController). */
    private function expectedVersion(Request $request): ?int
    {
        $header = trim((string) $request->header('If-Match', ''));
        $header = trim($header, '"');

        return $header === '' || ! ctype_digit($header) ? null : (int) $header;
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
