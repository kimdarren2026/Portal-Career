<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Actions\AddCompanyMember;
use App\Domains\Company\Actions\ChangeCompanyMemberRole;
use App\Domains\Company\Actions\RevokeCompanyMember;
use App\Domains\Company\Exceptions\CompanyMemberAlreadyActive;
use App\Domains\Company\Exceptions\CompanyMemberNotFound;
use App\Domains\Company\Exceptions\CompanyNotFound;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AddCompanyMemberRequest;
use App\Http\Requests\Company\ChangeCompanyMemberRoleRequest;
use App\Http\Responses\ContractResponse;
use App\Http\Responses\CompanyMemberPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Company membership surface (FR-COMP-004, closed D-1).
 *
 * Reads are COMPANY_SCOPE; mutations additionally require the Company Admin
 * capability. The last active COMPANY_ADMIN can never be demoted or revoked.
 */
final class CompanyMemberController extends Controller
{
    public function index(Request $request, int $company): JsonResponse
    {
        $model = $this->scopedCompany($request, $company);
        if (Gate::forUser($request->user())->denies('view', $model)) {
            return $this->forbidden($request);
        }

        return ContractResponse::success($request, [
            'items' => $model->members()->active()->orderBy('id')->get()
                ->map(CompanyMemberPresenter::present(...))->values()->all(),
        ]);
    }

    public function store(AddCompanyMemberRequest $request, int $company, AddCompanyMember $action): JsonResponse
    {
        $model = $this->scopedCompany($request, $company);
        if (Gate::forUser($request->user())->denies('manageMembers', $model)) {
            return $this->forbidden($request);
        }

        $data = $request->validated();

        try {
            $member = $action->execute($this->actor($request), $model, $data['email'], $data['company_role']);
        } catch (CompanyMemberAlreadyActive) {
            return ContractResponse::error($request, 'MEMBER_ALREADY_ACTIVE', 409, 'Anggota ini sudah aktif di perusahaan tersebut.');
        }

        return ContractResponse::success($request, CompanyMemberPresenter::present($member), 201, ['EMAIL_DELIVERY_PENDING']);
    }

    public function update(ChangeCompanyMemberRoleRequest $request, int $company, int $member, ChangeCompanyMemberRole $action): JsonResponse
    {
        $model = $this->scopedCompany($request, $company);
        if (Gate::forUser($request->user())->denies('manageMembers', $model)) {
            return $this->forbidden($request);
        }

        $updated = $action->execute(
            $this->actor($request),
            $model,
            $this->scopedMember($model, $member),
            $request->validated()['company_role'],
        );

        return ContractResponse::success($request, CompanyMemberPresenter::present($updated));
    }

    public function destroy(Request $request, int $company, int $member, RevokeCompanyMember $action): JsonResponse
    {
        $model = $this->scopedCompany($request, $company);
        $target = $this->scopedMember($model, $member);

        // Leaving is the member's own act; revoking anyone else needs the
        // Company Admin capability.
        $isSelf = (int) $target->user_id === (int) $this->actor($request)->getKey();
        if (! $isSelf && Gate::forUser($request->user())->denies('manageMembers', $model)) {
            return $this->forbidden($request);
        }

        $action->execute($this->actor($request), $model, $target);

        return ContractResponse::success($request, ['status' => 'MEMBER_REVOKED']);
    }

    private function scopedCompany(Request $request, int $id): Company
    {
        return CompanyScope::findFor($this->actor($request), $id) ?? throw new CompanyNotFound();
    }

    /** Membership is resolved through its company, so a foreign id is 404, never 403. */
    private function scopedMember(Company $company, int $memberId): CompanyMember
    {
        return $company->members()->whereKey($memberId)->first() ?? throw new CompanyMemberNotFound();
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
