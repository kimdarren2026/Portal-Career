<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\SelectionSchedule\Actions\CancelSelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Actions\CreateSelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Actions\RescheduleSelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\SelectionScheduleNotFound;
use App\Domains\Recruitment\SelectionSchedule\Models\SelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Presenters\SelectionSchedulePresenter;
use App\Domains\Recruitment\SelectionSchedule\Queries\ListSelectionSchedules;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleScope;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Requests\SelectionSchedule\CancelSelectionScheduleRequest;
use App\Http\Requests\SelectionSchedule\CreateSelectionScheduleRequest;
use App\Http\Requests\SelectionSchedule\ListSelectionSchedulesRequest;
use App\Http\Requests\SelectionSchedule\RescheduleSelectionScheduleRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Selection Schedule Foundation v1 (SS-1, SS-2, SS-3, SS-5, SS-8, SS-9, all
 * approved and CLOSED). `COMPANY` vacancies only — `CAMPUS_SCOPE` is not
 * activated, no Campus vacancy runtime exists. `complete`/`no-show` remain
 * deliberately absent, as does the schedule attachment upload mechanism
 * (see the create contract's amendment note).
 */
final class SelectionScheduleController extends CandidateController
{
    public function __construct(
        CandidateProfileResolver $profiles,
        private readonly IdempotencyGuard $idempotency,
    ) {
        parent::__construct($profiles);
    }

    public function store(CreateSelectionScheduleRequest $request, int $application, CreateSelectionSchedule $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = RecruiterApplicationScope::findFor($actor, $application) ?? throw new ApplicationNotFound();

        return $this->idempotent($request, $actor, 'schedule.create', $application, function () use ($actor, $model, $request, $action): array {
            $schedule = $action->execute($actor, $model, $request->validated());

            return SelectionSchedulePresenter::operational($schedule);
        }, 201);
    }

    public function index(
        ListSelectionSchedulesRequest $request,
        ListSelectionSchedules $query,
    ): JsonResponse {
        $actor = $this->actor($request);
        $filters = array_intersect_key($request->query(), array_flip(ListSelectionSchedules::FILTERS));
        $sort = $request->string('sort', 'starts_at')->toString();
        $direction = $request->string('direction', 'asc')->toString();

        if ($this->isOperationalActor($actor)) {
            $schedules = $query->execute(SelectionScheduleScope::operationalQueryFor($actor), $filters, $sort, $direction);

            return ContractResponse::success($request, $this->paginated($schedules, SelectionSchedulePresenter::operational(...)));
        }

        if (ApplicationScope::isCandidateActor($actor)) {
            $profile = $this->ownProfile($request);
            $schedules = $query->execute(SelectionScheduleScope::candidateQueryFor($profile), $filters, $sort, $direction);

            return ContractResponse::success($request, $this->paginated($schedules, SelectionSchedulePresenter::candidate(...)));
        }

        return $this->forbidden($request);
    }

    public function show(Request $request, int $schedule): JsonResponse
    {
        $actor = $this->actor($request);

        if ($this->isOperationalActor($actor)) {
            $model = SelectionScheduleScope::findFor($actor, $schedule) ?? throw new SelectionScheduleNotFound();

            return ContractResponse::success($request, SelectionSchedulePresenter::operational($model));
        }

        if (ApplicationScope::isCandidateActor($actor)) {
            $profile = $this->ownProfile($request);
            $model = SelectionScheduleScope::candidateFindFor($profile, $schedule) ?? throw new SelectionScheduleNotFound();

            return ContractResponse::success($request, SelectionSchedulePresenter::candidate($model));
        }

        return $this->forbidden($request);
    }

    public function history(Request $request, int $schedule): JsonResponse
    {
        $actor = $this->actor($request);

        if ($this->isOperationalActor($actor)) {
            $model = SelectionScheduleScope::findFor($actor, $schedule) ?? throw new SelectionScheduleNotFound();
            $events = $model->histories()->orderBy('occurred_at')->orderBy('id')->get()
                ->map(SelectionSchedulePresenter::historyOperational(...))->values()->all();

            return ContractResponse::success($request, ['items' => $events]);
        }

        if (ApplicationScope::isCandidateActor($actor)) {
            $profile = $this->ownProfile($request);
            $model = SelectionScheduleScope::candidateFindFor($profile, $schedule) ?? throw new SelectionScheduleNotFound();
            $events = $model->histories()->orderBy('occurred_at')->orderBy('id')->get()
                ->map(SelectionSchedulePresenter::historyCandidate(...))->values()->all();

            return ContractResponse::success($request, ['items' => $events]);
        }

        return $this->forbidden($request);
    }

    public function reschedule(RescheduleSelectionScheduleRequest $request, int $schedule, RescheduleSelectionSchedule $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = SelectionScheduleScope::findFor($actor, $schedule) ?? throw new SelectionScheduleNotFound();

        return $this->idempotent($request, $actor, 'schedule.reschedule', $schedule, function () use ($actor, $model, $request, $action): array {
            $rescheduled = $action->execute($actor, $model, $request->validated(), $this->expectedVersion($request));

            return SelectionSchedulePresenter::operational($rescheduled);
        });
    }

    public function cancel(CancelSelectionScheduleRequest $request, int $schedule, CancelSelectionSchedule $action): JsonResponse
    {
        $actor = $this->actor($request);

        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $model = SelectionScheduleScope::findFor($actor, $schedule) ?? throw new SelectionScheduleNotFound();

        return $this->idempotent($request, $actor, 'schedule.cancel', $schedule, function () use ($actor, $model, $request, $action): array {
            $cancelled = $action->execute($actor, $model, $request->filled('reason') ? $request->string('reason')->toString() : null);

            return SelectionSchedulePresenter::operational($cancelled);
        });
    }

    private function isRecruiterActor(User $actor): bool
    {
        return SelectionScheduleScope::isRecruiterOrAdmin($actor) || SelectionScheduleScope::isSuperAdmin($actor) || \App\Domains\Vacancy\Support\CampusScope::isCampusAdmin($actor); // CAMPUS_SCOPE (HR_ADMIN)
    }

    /** Read-eligible non-candidate actors: recruiter/admin, Super Admin, and Auditor (READ_ONLY). */
    private function isOperationalActor(User $actor): bool
    {
        return SelectionScheduleScope::isRecruiterOrAdmin($actor)
            || SelectionScheduleScope::isSuperAdmin($actor)
            || SelectionScheduleScope::isAuditor($actor)
            || \App\Domains\Vacancy\Support\CampusScope::isCampusAdmin($actor); // CAMPUS_SCOPE (HR_ADMIN)
    }

    /** `If-Match` carries the `revision_number` the client last read, mirroring `ApplicationController`. */
    private function expectedVersion(Request $request): ?int
    {
        $header = trim((string) $request->header('If-Match', ''));
        $header = trim($header, '"');

        return $header === '' || ! ctype_digit($header) ? null : (int) $header;
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
