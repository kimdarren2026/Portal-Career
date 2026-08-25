<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Actions\CreateCompany;
use App\Domains\Company\Actions\ReviewCompanyVerification;
use App\Domains\Company\Actions\SubmitCompanyVerification;
use App\Domains\Company\Actions\UpdateCompanyProfile;
use App\Domains\Company\Enums\CompanyReviewAction;
use App\Domains\Company\Exceptions\CompanyAlreadyExistsForUser;
use App\Domains\Company\Exceptions\CompanyInvalidTransition;
use App\Domains\Company\Exceptions\CompanyNotFound;
use App\Domains\Company\Exceptions\CompanyProfileIncomplete;
use App\Domains\Company\Exceptions\ReviewReasonRequired;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Support\CompanyDuplicateSignals;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\ReviewCompanyRequest;
use App\Http\Requests\Company\SubmitCompanyVerificationRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CompanyController extends Controller
{
    public function create(CreateCompanyRequest $request, CreateCompany $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        if (Gate::forUser($actor)->denies('create', Company::class)) { return $this->forbidden($request); }
        $attributes = $request->validated();
        // INV-034: a similar company is flagged for review, never hard-rejected.
        $duplicateSignals = CompanyDuplicateSignals::detect($attributes);
        try { $company = $action->execute($actor, $attributes); }
        catch (CompanyAlreadyExistsForUser) { return ContractResponse::error($request, 'COMPANY_ALREADY_EXISTS_FOR_USER', 409, 'Recruiter sudah memiliki profil perusahaan.'); }
        $warnings = $duplicateSignals === [] ? [] : ['COMPANY_DUPLICATE_REVIEW_SUGGESTED'];

        return ContractResponse::success($request, $this->present($company, [], false), 201, $warnings)
            ->header('Location', route('companies.show', ['company' => $company->getKey()]));
    }

    public function show(Request $request, int $company): JsonResponse
    {
        $model = $this->scopedCompany($request, $company);
        if (Gate::forUser($request->user())->denies('view', $model)) { return $this->forbidden($request); }
        $includes = array_filter(array_map('trim', explode(',', $request->string('include')->toString())));
        return ContractResponse::success($request, $this->present($model, $includes, $this->canSeeInternal($request->user())));
    }

    public function update(UpdateCompanyRequest $request, int $company, UpdateCompanyProfile $action): JsonResponse
    {
        $model = $this->scopedCompany($request, $company);
        if (Gate::forUser($request->user())->denies('update', $model)) { return $this->forbidden($request); }
        try { $model = $action->execute($request->user(), $model, $request->validated()); }
        catch (CompanyInvalidTransition) { return ContractResponse::error($request, 'COMPANY_INVALID_TRANSITION', 409, 'Perusahaan tidak dapat diubah pada status ini.'); }
        return ContractResponse::success($request, $this->present($model, [], false));
    }

    public function submit(SubmitCompanyVerificationRequest $request, int $company, SubmitCompanyVerification $action): JsonResponse
    {
        $model = $this->scopedCompany($request, $company);
        if (Gate::forUser($request->user())->denies('submit', $model)) { return $this->forbidden($request); }
        try { $model = $action->execute($request->user(), $model); }
        catch (CompanyInvalidTransition) { return ContractResponse::error($request, 'COMPANY_INVALID_TRANSITION', 409, 'Transisi status perusahaan tidak sah.'); }
        catch (CompanyProfileIncomplete $e) { return ContractResponse::error($request, 'COMPANY_PROFILE_INCOMPLETE', 422, 'Profil perusahaan belum lengkap.', ['missing' => $e->missing]); }
        return ContractResponse::success($request, $this->present($model, [], false));
    }

    public function review(ReviewCompanyRequest $request, int $company, string $action, ReviewCompanyVerification $review): JsonResponse
    {
        $model = CompanyScope::findFor($request->user(), $company) ?? throw new CompanyNotFound();
        if (Gate::forUser($request->user())->denies('review', $model)) { return $this->forbidden($request); }
        $reviewAction = CompanyReviewAction::tryFrom(str_replace('-', '_', strtoupper($action)));
        if ($reviewAction === null || ! in_array($reviewAction, [CompanyReviewAction::Verify, CompanyReviewAction::RequestRevision, CompanyReviewAction::Reject, CompanyReviewAction::Suspend, CompanyReviewAction::Restore], true)) {
            return ContractResponse::error($request, 'COMPANY_INVALID_TRANSITION', 409, 'Aksi verifikasi tidak sah.');
        }
        try { $model = $review->execute($request->user(), $model, $reviewAction, $request->validated()); }
        catch (ReviewReasonRequired) { return ContractResponse::error($request, 'REVIEW_REASON_REQUIRED', 422, 'Reason wajib diisi.'); }
        catch (CompanyInvalidTransition) { return ContractResponse::error($request, 'COMPANY_INVALID_TRANSITION', 409, 'Transisi status perusahaan tidak sah.'); }
        return ContractResponse::success($request, $this->present($model, [], true));
    }

    private function scopedCompany(Request $request, int $id): Company
    {
        return CompanyScope::findFor($request->user(), $id) ?? throw new CompanyNotFound();
    }

    /** @param list<string> $includes @return array<string, mixed> */
    private function present(Company $company, array $includes, bool $internal): array
    {
        $data = [
            'id' => (int) $company->getKey(), 'name' => $company->name,
            'normalized_name' => $company->normalized_name, 'organization_type_id' => $company->organization_type_id,
            'industry_id' => $company->industry_id, 'website' => $company->website,
            'official_email' => $company->official_email, 'official_phone' => $company->official_phone,
            'address' => $company->address, 'province_geographic_area_id' => $company->province_geographic_area_id,
            'city_geographic_area_id' => $company->city_geographic_area_id, 'legal_identifier' => $company->legal_identifier,
            'verification_status' => $company->verification_status->value, 'verified_at' => $company->verified_at?->toIso8601String(),
            'suspended_at' => $company->suspended_at?->toIso8601String(), 'mitra_kampus_active' => DB::table('partnerships')->where('company_id', $company->getKey())->where('status', 'ACTIVE')->where('start_date', '<=', now()->toDateString())->where(function ($q): void { $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString()); })->exists(),
        ];
        if (in_array('members', $includes, true)) { $data['members'] = $company->members()->active()->get()->map(fn ($m) => ['id' => (int) $m->id, 'user_id' => (int) $m->user_id, 'company_role' => $m->company_role, 'status' => $m->status])->values()->all(); }
        if (in_array('documents', $includes, true)) { $data['documents'] = $company->documents()->whereNull('superseded_at')->get()->map(fn ($d) => ['id' => (int) $d->id, 'document_type' => $d->document_type, 'document_number' => $d->document_number, 'issued_at' => $d->issued_at?->toDateString(), 'expires_at' => $d->expires_at?->toDateString(), 'status' => $d->status])->values()->all(); }
        if (in_array('verification_history', $includes, true)) {
            $data['verification_history'] = $company->verificationReviews()->orderBy('reviewed_at')->get()->map(function ($review) use ($internal): array {
                $row = ['id' => (int) $review->id, 'action' => $review->action->value, 'from_status' => $review->from_status, 'to_status' => $review->to_status, 'reason_category' => $review->reason_category, 'recruiter_visible_note' => $review->recruiter_visible_note, 'reviewed_at' => $review->reviewed_at?->toIso8601String()];
                if ($internal) { $row['internal_note'] = $review->internal_note; }
                return $row;
            })->values()->all();
        }
        return $data;
    }

    private function canSeeInternal(User $user): bool
    {
        return $user->hasActiveRole(\App\Domains\Identity\Enums\RoleCode::CareerCenterStaff)
            || $user->hasActiveRole(\App\Domains\Identity\Enums\RoleCode::CareerCenterManager)
            || $user->hasActiveRole(\App\Domains\Identity\Enums\RoleCode::Auditor)
            || $user->hasActiveRole(\App\Domains\Identity\Enums\RoleCode::SuperAdmin);
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', Response::HTTP_FORBIDDEN, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
