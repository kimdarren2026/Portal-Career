<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Support\CompanyProfilePresenter;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Models\User;
use App\Domains\MasterData\Queries\GetPublicReferenceData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Non-mutating Inertia delivery for the recruiter "Profil Perusahaan" and
 * "Status Verifikasi" pages (Recruiter Company & Vacancy Frontend Slice v3).
 *
 * Every mutation still posts to the frozen JSON routes (`companies.store`,
 * `companies.update`, `companies.submit`). This controller reads only what a
 * recruiter is already authorized to see: the company is resolved through
 * `CompanyScope` (ACTIVE membership; out-of-scope rows are absent → 404), and
 * `CompanyProfilePresenter` never returns `internal_note` or any
 * Career-Center-internal verification field.
 *
 * A recruiter with no company yet still reaches the page — it renders the
 * onboarding create form, which posts to the frozen `POST /companies` route.
 * Verification approve/reject/suspend/restore are Career Center authority and
 * appear nowhere here.
 */
final class CompanyPageController extends Controller
{
    /** `GET /profil-perusahaan`. */
    public function profile(Request $request, GetPublicReferenceData $referenceData): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        $company = CompanyScope::queryFor($actor)->orderBy('id')->first();

        if ($company === null) {
            if (! $this->mayOnboard($actor)) {
                return $this->forbidden($request);
            }

            return Inertia::render('recruiter/ProfilPerusahaan', [
                'company' => null,
                'can_create' => true,
                'can_edit' => false,
                'reference_data' => $referenceData->execute(),
            ]);
        }

        return Inertia::render('recruiter/ProfilPerusahaan', [
            'company' => CompanyProfilePresenter::profile($company),
            'can_create' => false,
            'can_edit' => $this->isEditableState($company)
                && ! Gate::forUser($actor)->denies('update', $company),
            'reference_data' => $referenceData->execute(),
        ]);
    }

    /** `GET /status-verifikasi`. */
    public function verification(Request $request): Response|JsonResponse|SymfonyResponse
    {
        $actor = $this->actor($request);
        $company = CompanyScope::queryFor($actor)->orderBy('id')->first();

        if ($company === null) {
            if (! $this->mayOnboard($actor)) {
                return $this->forbidden($request);
            }

            return Inertia::render('recruiter/StatusVerifikasi', [
                'company' => null,
                'trail' => [],
                'can_submit' => false,
            ]);
        }

        return Inertia::render('recruiter/StatusVerifikasi', [
            'company' => [
                'id' => (int) $company->getKey(),
                'name' => $company->name,
                'verification_status' => $company->verification_status->value,
                'verified_at' => $company->verified_at?->toIso8601String(),
                'suspended_at' => $company->suspended_at?->toIso8601String(),
            ],
            'trail' => CompanyProfilePresenter::verificationTrail($company),
            // Submit / resubmit is the only recruiter verification action, and
            // only from DRAFT or REVISION_REQUIRED (SubmitCompanyVerification).
            'can_submit' => $this->isEditableState($company)
                && ! Gate::forUser($actor)->denies('submit', $company),
        ]);
    }

    /** DRAFT / REVISION_REQUIRED — the only states the frozen update & submit actions accept. */
    private function isEditableState(Company $company): bool
    {
        return in_array($company->verification_status, [CompanyStatus::Draft, CompanyStatus::RevisionRequired], true);
    }

    private function mayOnboard(User $actor): bool
    {
        return ! Gate::forUser($actor)->denies('create', Company::class);
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
