<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Notification\Actions\RequeueEmailOutboxMessage;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin transactional email-outbox operations (PGC-V1 / PD-B).
 * `SUPER_ADMIN` only. The outbox delivery/transport state is admin-only and is
 * never exposed on the candidate notification surface (item 59).
 */
final class AdminEmailOutboxController extends Controller
{
    /** `GET /admin/email-outbox` — a small operational view of undelivered / dead-letter rows. */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->forbidden($request);
        }

        $status = $request->string('status')->toString();
        $rows = DB::table('email_outbox')
            ->when(in_array($status, ['PENDING', 'PROCESSING', 'SENT', 'FAILED_RETRYABLE', 'DEAD_LETTER'], true),
                fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'recipient', 'template_reference', 'related_object_type', 'related_object_id', 'status', 'attempt_count', 'next_attempt_at', 'last_error_summary', 'sent_at', 'created_at']);

        return ContractResponse::success($request, ['items' => $rows]);
    }

    /** `POST /admin/email-outbox/{message}/requeue`. */
    public function requeue(Request $request, int $message, RequeueEmailOutboxMessage $action): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->forbidden($request);
        }

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $message);

        return ContractResponse::success($request, ['id' => $message, 'status' => 'PENDING']);
    }

    private function isSuperAdmin(Request $request): bool
    {
        /** @var User|null $user */
        $user = $request->user();

        return $user !== null && $user->hasActiveRole(RoleCode::SuperAdmin);
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
