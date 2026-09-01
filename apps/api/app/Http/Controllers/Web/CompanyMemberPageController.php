<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AddCompanyMemberRequest;
use App\Http\Responses\CompanyMemberPresenter;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the recruiter "Anggota Perusahaan" page
 * (Frontend Vertical Slice v5) — activates the canonical recruiter nav item.
 *
 * FR-COMP-004 + closed D-1. Reads reuse the frozen membership read model:
 * `company->members()->active()` presented through `CompanyMemberPresenter`,
 * exactly the payload `CompanyMemberController::index` already returns
 * (`id`, `user_id`, `company_role`, `status`, `joined_at`, `revoked_at` — no
 * name or email, a frozen privacy boundary). The company is resolved by the
 * actor's own ACTIVE membership (never the global-reader `CompanyScope`), so
 * a Career Center / Auditor / Candidate actor with no membership is simply
 * forbidden.
 *
 * Every mutation still posts to the frozen JSON routes:
 * add → `POST /companies/{company}/members`,
 * change role → `PATCH /companies/{company}/members/{member}`,
 * revoke / leave → `DELETE /companies/{company}/members/{member}`.
 * The last-active-admin invariant and the "unknown email is NOT SUPPORTED"
 * rule are enforced entirely by those Actions; this page only surfaces their
 * frozen error contracts.
 */
final class CompanyMemberPageController extends Controller
{
    /** `GET /anggota-perusahaan`. */
    public function index(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        $company = $this->membershipCompany($actor);

        if ($company === null || Gate::forUser($actor)->denies('view', $company)) {
            return $this->forbidden($request);
        }

        return Inertia::render('recruiter/AnggotaPerusahaan', [
            'company' => ['id' => (int) $company->getKey(), 'name' => $company->name],
            'members' => $company->members()->active()->orderBy('id')->get()
                ->map(CompanyMemberPresenter::present(...))->values()->all(),
            'can_manage' => ! Gate::forUser($actor)->denies('manageMembers', $company),
            'current_user_id' => (int) $actor->getKey(),
            'roles' => AddCompanyMemberRequest::ROLES,
        ]);
    }

    /**
     * The actor's OWN company — the one they hold an ACTIVE, non-revoked
     * membership of (OL-1). A recruiter has at most one company
     * (`COMPANY_ALREADY_EXISTS_FOR_USER`).
     */
    private function membershipCompany(User $actor): ?Company
    {
        return Company::query()
            ->whereHas('members', fn ($q) => $q
                ->where('user_id', $actor->getKey())
                ->where('status', 'ACTIVE')
                ->whereNull('revoked_at'))
            ->orderBy('id')
            ->first();
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
