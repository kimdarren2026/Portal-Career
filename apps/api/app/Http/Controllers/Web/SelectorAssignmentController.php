<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Domains\Vacancy\Actions\AssignSelectorToStage;
use App\Domains\Vacancy\Actions\RevokeSelectorAssignment;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotFound;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\SelectionStageAssignment;
use App\Domains\Vacancy\Support\SelectorAssignmentScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Selection\AssignSelectorRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Selector stage-assignment surface (API_CONTRACT.md Part VIII · FR-HR-006 ·
 * INV-037 · AUTHORIZATION_MATRIX.md §4.8).
 *
 * `HR_ADMIN` (Admin Kepegawaian) and `SUPER_ADMIN` only — assignment is a
 * campus-recruitment capability (footnote 23), so the stage must belong to a
 * `CAMPUS` vacancy or the caller gets a plain 404. **A selector can never
 * assign, extend, or revoke assignments, including their own** (footnote 24);
 * recruiters and every other persona are `DENY`. Holding the SELECTOR role is
 * only a precondition for *being* assigned and grants nothing here.
 */
final class SelectorAssignmentController extends Controller
{
    public function __construct(private readonly IdempotencyGuard $idempotency) {}

    /** `GET /stages/{stage}/selector-assignments` — full roster, revoked history included. */
    public function index(Request $request, int $stage): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $this->mayManage($actor)) {
            return $this->forbidden($request);
        }

        $stageModel = $this->campusStageOrFail($stage);

        $rows = SelectorAssignmentScope::rosterForStage((int) $stageModel->getKey())
            ->with('selector:id,name')
            ->get()
            ->map(fn (SelectionStageAssignment $row): array => $this->present($row))
            ->all();

        return ContractResponse::success($request, ['items' => $rows]);
    }

    /** `POST /stages/{stage}/selector-assignments`. */
    public function store(AssignSelectorRequest $request, int $stage, AssignSelectorToStage $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $this->mayManage($actor)) {
            return $this->forbidden($request);
        }

        $selectorUserId = $request->selectorUserId();

        $begin = $this->idempotency->begin(
            $request->header('Idempotency-Key'),
            $actor,
            'selection.selector-assignment.assign',
            ['stage_id' => $stage, 'selector_user_id' => $selectorUserId],
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
            $assignment = $action->execute($actor, $stage, $selectorUserId);
        } catch (Throwable $exception) {
            $this->idempotency->fail($id);

            throw $exception;
        }

        $data = ['assignment' => $this->present($assignment)];
        $this->idempotency->complete($id, 201, ['data' => $data]);

        return ContractResponse::success($request, $data, 201);
    }

    /** `POST /selector-assignments/{assignment}/revoke`. */
    public function revoke(Request $request, int $assignment, RevokeSelectorAssignment $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $this->mayManage($actor)) {
            return $this->forbidden($request);
        }

        $begin = $this->idempotency->begin(
            $request->header('Idempotency-Key'),
            $actor,
            'selection.selector-assignment.revoke',
            ['assignment_id' => $assignment],
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
            $revoked = $action->execute($actor, $assignment);
        } catch (Throwable $exception) {
            $this->idempotency->fail($id);

            throw $exception;
        }

        $data = ['assignment' => $this->present($revoked)];
        $this->idempotency->complete($id, 200, ['data' => $data]);

        return ContractResponse::success($request, $data);
    }

    private function mayManage(User $actor): bool
    {
        return $actor->hasActiveRole(RoleCode::HrAdmin) || $actor->hasActiveRole(RoleCode::SuperAdmin);
    }

    private function campusStageOrFail(int $stageId): RecruitmentStage
    {
        /** @var RecruitmentStage|null $stage */
        $stage = RecruitmentStage::query()->whereKey($stageId)->first();
        if ($stage === null || $stage->vacancy()->value('ownership_type') !== 'CAMPUS') {
            throw new RecruitmentStageNotFound();
        }

        return $stage;
    }

    /** @return array<string, mixed> */
    private function present(SelectionStageAssignment $row): array
    {
        return [
            'id' => (int) $row->getKey(),
            'recruitment_stage_id' => (int) $row->recruitment_stage_id,
            'selector_user_id' => (int) $row->selector_user_id,
            'selector_name' => $row->relationLoaded('selector') ? $row->selector?->name : null,
            'assigned_by_user_id' => (int) $row->assigned_by_user_id,
            'assigned_at' => $row->assigned_at?->toIso8601String(),
            'revoked_at' => $row->revoked_at?->toIso8601String(),
            'revoked_by_user_id' => $row->revoked_by_user_id !== null ? (int) $row->revoked_by_user_id : null,
            'active' => $row->revoked_at === null,
        ];
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
}
