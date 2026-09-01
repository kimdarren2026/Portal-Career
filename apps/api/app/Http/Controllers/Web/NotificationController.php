<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Models\User;
use App\Domains\Notification\Actions\MarkAllNotificationsRead;
use App\Domains\Notification\Actions\MarkNotificationRead;
use App\Domains\Notification\Exceptions\NotificationNotFound;
use App\Domains\Notification\Queries\ListNotifications;
use App\Domains\Notification\Support\NotificationPresenter;
use App\Domains\Notification\Support\NotificationScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app notification centre (API_CONTRACT.md Part IX · FSD §4.2 *Notifikasi*).
 *
 * Reclassified `VERSIONED_API → INERTIA_WEB` for the MVP browser portal by an
 * approved Product Owner / SPEC-DOC decision (see the Part X decision row) —
 * a transport classification only; `OWN` authorization and every business
 * rule are unchanged. Served over the Laravel session guard with CSRF on the
 * two mutations, the same SPEC-DOC-05/-07/-08/SS-9/OF-2 pattern.
 *
 * `OWN` by `user_id`, resolved server-side through `NotificationScope`.
 * Company membership never widens the set. The two mutations return `204`.
 * Reading one's own notifications is not an FR-AUD-001 event — no audit.
 */
final class NotificationController extends Controller
{
    /** `GET /notifications` — paginated list plus `meta.unread_count`. */
    public function index(ListNotificationsRequest $request, ListNotifications $query): JsonResponse
    {
        $actor = $this->actor($request);

        $filters = array_intersect_key($request->query(), array_flip(ListNotifications::FILTERS));
        $page = $query->execute(NotificationScope::queryFor($actor), $filters);

        return response()->json([
            'data' => [
                'items' => collect($page->items())->map(NotificationPresenter::summary(...))->values()->all(),
                'pagination' => [
                    'page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ],
            'meta' => [
                'correlation_id' => $request->attributes->get('correlation_id'),
                'unread_count' => NotificationScope::unreadCount($actor),
            ],
        ]);
    }

    /** `POST /notifications/{notification}/read` — `204`, idempotent, OWN only. */
    public function read(Request $request, int $notification, MarkNotificationRead $action): JsonResponse
    {
        $actor = $this->actor($request);
        $model = NotificationScope::findFor($actor, $notification) ?? throw new NotificationNotFound();

        $action->execute($model);

        return response()->json(null, 204);
    }

    /** `POST /notifications/read-all` — `204`, affects only the actor's own unread rows. */
    public function readAll(Request $request, MarkAllNotificationsRead $action): JsonResponse
    {
        $action->execute($this->actor($request));

        return response()->json(null, 204);
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
