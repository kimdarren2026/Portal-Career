<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Actions\RestoreUserAccount;
use App\Domains\Identity\Actions\SuspendUserAccount;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Queries\ListUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin user directory and account lifecycle (PGC-V1 / PD-F).
 * `SUPER_ADMIN` only on every method. MVP lifecycle is `ACTIVE ↔ SUSPENDED`
 * only — `DISABLED` is not exposed. No secret is ever returned; no
 * notification is sent on suspension; every mutation is audited.
 */
final class AdminUserController extends Controller
{
    /** `GET /admin/users`. */
    public function index(ListUsersRequest $request, ListUsers $query): JsonResponse
    {
        if (! $this->actor($request)->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $page = $query->execute($request->validated());

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

    /** `POST /admin/users/{user}/suspend`. */
    public function suspend(SuspendUserRequest $request, User $user, SuspendUserAccount $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $suspended = $action->execute($actor, $user, $request->reason());

        return ContractResponse::success($request, $this->summary($suspended));
    }

    /** `POST /admin/users/{user}/restore`. */
    public function restore(Request $request, User $user, RestoreUserAccount $action): JsonResponse
    {
        $actor = $this->actor($request);
        if (! $actor->hasActiveRole(RoleCode::SuperAdmin)) {
            return $this->forbidden($request);
        }

        $reason = $request->filled('reason') ? trim((string) $request->input('reason')) : null;
        $restored = $action->execute($actor, $user, $reason);

        return ContractResponse::success($request, $this->summary($restored));
    }

    /** @return array<string, mixed> */
    private function summary(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'status' => $user->status->value,
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
