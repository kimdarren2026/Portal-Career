<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Identity\Actions\GetCurrentUserContext;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the recruiter "Pengaturan Akun" page
 * (Frontend Vertical Slice v6) — activates the canonical recruiter nav item.
 *
 * Read only. The account context is the frozen `GET /me` shape
 * (`GetCurrentUserContext`); this page shows the self-only subset the account
 * surface needs — name, email, email-verification state, account status,
 * roles, company memberships. Every mutation posts to a frozen JSON route:
 * password change → `PUT /me/password` (`ChangeOwnPassword`, current-password
 * verified, FR-AUTH-006 policy), resend verification email →
 * `POST /auth/resend-verification`, sign out → `POST /auth/logout`.
 *
 * No email-address change, 2FA, avatar, account deletion, session-revocation
 * list or preference surface is rendered — none has a frozen contract.
 *
 * Persona gate mirrors the other recruiter page controllers: this is the
 * recruiter account surface. The universal `/me` + `/me/password` routes are
 * unchanged and remain every persona's mechanism; a candidate/other-persona
 * account surface is a separate slice.
 */
final class AccountSettingsPageController extends Controller
{
    /** `GET /pengaturan-akun`. */
    public function index(Request $request, GetCurrentUserContext $context): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        if (! $this->isRecruiterActor($actor)) {
            return $this->forbidden($request);
        }

        $me = $context->execute($actor);

        return Inertia::render('recruiter/PengaturanAkun', [
            'account' => [
                'name' => $me['user']['name'],
                'email' => $me['user']['email'],
                'email_verified_at' => $me['user']['email_verified_at'],
                'status' => $me['user']['status'],
            ],
            'roles' => $me['roles'],
            'company_memberships' => $me['company_memberships'],
        ]);
    }

    private function isRecruiterActor(User $actor): bool
    {
        return $actor->hasActiveRole(RoleCode::CompanyRecruiter)
            || $actor->hasActiveRole(RoleCode::CompanyAdmin)
            || $actor->hasActiveRole(RoleCode::SuperAdmin);
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
