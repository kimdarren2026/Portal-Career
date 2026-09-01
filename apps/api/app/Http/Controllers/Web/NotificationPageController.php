<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Notification\Queries\ListNotifications;
use App\Domains\Notification\Support\NotificationPresenter;
use App\Domains\Notification\Support\NotificationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the recruiter "Notifikasi" page — the
 * browser realization of `GET /notifications` (API_CONTRACT.md Part IX),
 * reclassified `INERTIA_WEB` by an approved Product Owner / SPEC-DOC decision.
 *
 * Reads reuse the frozen `ListNotifications` / `NotificationScope` /
 * `NotificationPresenter` directly (never a loopback HTTP call). `OWN` by
 * `user_id`; company membership never widens the set. The two mutations
 * (`mark read`, `mark all read`) still post to the frozen JSON routes.
 *
 * Each row is enriched with a `link` ONLY when its source-backed
 * `related_object_type` + `related_object_id` map unambiguously to an
 * existing recruiter route; the target route enforces its own server-side
 * scope, so a stale or foreign id resolves to that route's own 404.
 *
 * Persona gate mirrors the other recruiter page controllers. The frozen
 * `GET /notifications` JSON route stays open to every authenticated persona;
 * a candidate / Career Center notification page is a separate slice.
 */
final class NotificationPageController extends Controller
{
    /** `GET /notifikasi` — persona-multiplexed, OWN-scoped for every persona. */
    public function index(ListNotificationsRequest $request, ListNotifications $query): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);

        if ($this->isRecruiterActor($actor)) {
            $component = 'recruiter/Notifikasi';
            $linker = self::link(...);
        } elseif ($this->isCareerCenter($actor)) {
            $component = 'career-center/Notifikasi';
            $linker = self::careerCenterLink(...);
        } elseif ($this->isSuperAdmin($actor)) {
            // Super Admin holds OWN notification access (AUTHORIZATION_MATRIX.md
            // §4.9 — every persona reads only its own rows; SPEC-DOC-09). It
            // gets a dedicated Super Admin inbox, never the recruiter page as a
            // fallback. "Notifikasi" is not a canonical Super Admin nav item.
            $component = 'super-admin/Notifikasi';
            $linker = self::superAdminLink(...);
        } else {
            return $this->forbidden($request);
        }

        $filters = array_intersect_key($request->query(), array_flip(ListNotifications::FILTERS));
        $page = $query->execute(NotificationScope::queryFor($actor), $filters);

        $items = collect($page->items())
            ->map(NotificationPresenter::summary(...))
            ->map(fn (array $row): array => $row + ['link' => $linker($row)])
            ->values()->all();

        return Inertia::render($component, [
            'items' => $items,
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'filters' => (object) $filters,
            'unread_count' => NotificationScope::unreadCount($actor),
        ]);
    }

    /**
     * Source-resolved deep link. Only the four `related_object_type` values
     * the current notifiers actually emit that have an authorized recruiter
     * route are mapped; every other value (or a missing id) yields `null`.
     *
     * @param array<string, mixed> $row
     */
    private static function link(array $row): ?string
    {
        $type = $row['related_object_type'] ?? null;
        $id = $row['related_object_id'] ?? null;

        return match ($type) {
            'vacancy' => $id === null ? null : "/kelola-lowongan/{$id}",
            'application' => $id === null ? null : "/pelamar/{$id}",
            'selection_schedule' => $id === null ? null : "/jadwal-seleksi/{$id}",
            // Company notifications are only ever written for the recruiter's
            // own company; the verification surface takes no id.
            'company' => '/status-verifikasi',
            default => null,
        };
    }

    /**
     * Career Center deep-link map — only the two `related_object_type` values
     * that have an authorised Career Center page. Everything else → no link.
     *
     * @param array<string, mixed> $row
     */
    private static function careerCenterLink(array $row): ?string
    {
        $id = $row['related_object_id'] ?? null;

        return match ($row['related_object_type'] ?? null) {
            'company' => $id === null ? null : "/verifikasi-perusahaan/{$id}",
            'vacancy' => $id === null ? null : "/moderasi-lowongan/{$id}",
            default => null,
        };
    }

    /**
     * Super Admin deep-link map. No canonical Super Admin page takes a
     * per-object route yet (Audit Log is a filtered list, not an object view),
     * so every notification type resolves to no link — the row still renders,
     * just without a "Lihat detail" affordance.
     *
     * @param array<string, mixed> $row
     */
    private static function superAdminLink(array $row): ?string
    {
        return null;
    }

    private function isRecruiterActor(User $actor): bool
    {
        // Super Admin is intentionally NOT here — a pure Super Admin must never
        // render the recruiter Notifikasi page as a shared-controller fallback
        // (Super Admin Control Plane v10, Phase 0). A Super Admin who also holds
        // an active company recruiter/admin role still matches on that role.
        return $actor->hasActiveRole(RoleCode::CompanyRecruiter)
            || $actor->hasActiveRole(RoleCode::CompanyAdmin);
    }

    private function isCareerCenter(User $actor): bool
    {
        return $actor->hasActiveRole(RoleCode::CareerCenterStaff)
            || $actor->hasActiveRole(RoleCode::CareerCenterManager);
    }

    private function isSuperAdmin(User $actor): bool
    {
        return $actor->hasActiveRole(RoleCode::SuperAdmin);
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
