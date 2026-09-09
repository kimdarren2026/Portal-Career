<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Actions\AssignUserRole;
use App\Domains\Identity\Actions\RevokeUserRole;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Exceptions\RoleAssignmentConflict;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\IdempotencyGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignUserRoleRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Mutation surface for Super Admin role management — the deterministic, frozen
 * part of "Pengguna dan Role" (API_CONTRACT.md `POST /admin/users/{user}/roles`
 * and its revoke pair; `AUTHORIZATION_MATRIX.md` §4.9 "List users · assign /
 * revoke roles" = `A` SUPER_ADMIN).
 *
 * `SUPER_ADMIN` only on every method — Auditor's `R` on this matrix row is a
 * read grant, and there is no user-directory read contract to expose yet, so
 * Auditor is denied here. No persona is broadened.
 *
 * The user-directory list (`GET /admin/users`), suspend/restore and the
 * `DISABLED` lifecycle are deliberately NOT routed: their field/filter/
 * pagination and session-termination contracts are unresolved.
 */
final class AdminUserRoleController extends Controller
{
    public function __construct(private readonly IdempotencyGuard $idempotency) {}

    /** `POST /admin/users/{user}/roles`. */
    public function assign(AssignUserRoleRequest $request, User $user, AssignUserRole $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $roleCode = $request->roleCode();

        $begin = $this->idempotency->begin(
            $request->header('Idempotency-Key'),
            $actor,
            'admin.user.role.assign',
            ['user_id' => $user->getKey(), 'role_code' => $roleCode],
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
            $assignment = $action->execute($actor, $user, $roleCode);
        } catch (RoleAssignmentConflict) {
            $this->idempotency->fail($id);

            return ContractResponse::error($request, 'CONFLICT', 409, 'Pengguna sudah memiliki role ini secara aktif.');
        } catch (Throwable $e) {
            $this->idempotency->fail($id);

            throw $e;
        }

        $data = [
            'assignment' => [
                'id' => (int) $assignment->getKey(),
                'user_id' => (int) $user->getKey(),
                'role_code' => $roleCode,
                'assigned_at' => optional($assignment->assigned_at)->toIso8601String(),
                'assigned_by' => (int) $actor->getKey(),
            ],
        ];

        $this->idempotency->complete($id, 201, ['data' => $data]);

        return ContractResponse::success($request, $data, 201);
    }

    /** `POST /admin/users/{user}/roles/{role}/revoke`. */
    public function revoke(Request $request, User $user, string $role, RevokeUserRole $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        if (RoleCode::tryFrom($role) === null) {
            return ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Kode role tidak dikenal.', [
                'fields' => ['role' => ['Kode role tidak dikenal.']],
            ]);
        }

        $result = $action->execute($actor, $user, $role);

        return ContractResponse::success($request, [
            'revoked' => $result['revoked'],
            'role_code' => $role,
            'user_id' => (int) $user->getKey(),
        ]);
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
