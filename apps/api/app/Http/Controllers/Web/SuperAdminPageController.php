<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Queries\ListAuditLogs;
use App\Domains\Audit\Support\AuditLogPresenter;
use App\Domains\Audit\Support\AuditLogScope;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditLogsRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the Super Admin control-plane pages
 * (Frontend Vertical Slice v10). Currently the single canonical Super Admin
 * module with a frozen runtime — "Audit Log" (FSD §4.6, §5.13 FR-AUD-001;
 * API_CONTRACT.md `GET /api/v1/audit-logs`, reclassified `INERTIA_WEB` with
 * the rest of the back-office surface, §Surface split).
 *
 * Read-only: `audit_logs` is physically append-only (DB role has SELECT/INSERT
 * only) and the contract defines no create/update/delete for any role,
 * Super Admin included (INV-016). Reads reuse the frozen `AuditLogScope` /
 * `ListAuditLogs` / `AuditLogPresenter` directly — never a loopback HTTP call.
 *
 * Explicit persona gate: `SUPER_ADMIN` or `AUDITOR`, both `READ_ONLY`
 * (AUTHORIZATION_MATRIX.md §4.9). Every other persona — recruiter, candidate,
 * Career Center, Admin Kepegawaian — receives the shared 403. Broad global
 * read authority elsewhere never substitutes for this check.
 */
final class SuperAdminPageController extends Controller
{
    /** `GET /audit-log`. */
    public function auditLogIndex(ListAuditLogsRequest $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! AuditLogScope::isAuditReader($actor)) {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->validated(), array_flip(ListAuditLogs::FILTERS));
        $perPage = $request->integer('per_page') ?: null;

        $page = app(ListAuditLogs::class)->execute(AuditLogScope::queryFor($actor), $filters, $perPage);

        $actorNames = $this->actorNames($page);

        $items = collect($page->items())
            ->map(AuditLogPresenter::summary(...))
            ->map(fn (array $row): array => $row + [
                'actor_name' => $row['actor_user_id'] === null ? null : ($actorNames[$row['actor_user_id']] ?? null),
            ])
            ->values()->all();

        return Inertia::render('super-admin/AuditLog', [
            'items' => $items,
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'filters' => (object) $filters,
            'action_options' => $this->distinctColumn('action'),
            'object_type_options' => $this->distinctColumn('object_type'),
        ]);
    }

    /**
     * Batched display-name lookup for the actors on the current page only —
     * one `whereIn`, never an N+1 relation load. Name only; email and every
     * other identity field stay out of the audit read model.
     *
     * @return array<int, string>
     */
    private function actorNames(LengthAwarePaginator $page): array
    {
        $ids = collect($page->items())
            ->pluck('actor_user_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')
            ->map(static fn ($name): string => (string) $name)->all();
    }

    /**
     * The distinct values actually present for a low-cardinality column, for
     * the filter dropdowns. This reflects real data — it invents no
     * vocabulary — and the audit action/object-type sets are small and bounded.
     *
     * @return list<string>
     */
    private function distinctColumn(string $column): array
    {
        return AuditLog::query()->select($column)->distinct()->orderBy($column)
            ->pluck($column)->filter()->values()->all();
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function forbidden(Request $request): Response|JsonResponse|SymfonyResponse
    {
        if ($request->expectsJson()) {
            return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
        }

        return Inertia::render('Error', ['status' => 403])->toResponse($request)->setStatusCode(403);
    }
}
