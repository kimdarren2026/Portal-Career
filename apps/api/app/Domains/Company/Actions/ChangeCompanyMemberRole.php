<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Company\Support\CompanyAdminProtection;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/**
 * PATCH /companies/{company}/members/{member} — change role (FR-COMP-004).
 *
 * Demoting the last active COMPANY_ADMIN is refused (closed D-1). The guard
 * runs inside this transaction so its company lock actually serializes two
 * concurrent demotions.
 */
final class ChangeCompanyMemberRole
{
    public function __construct(
        private readonly CompanyAdminProtection $adminProtection,
        private readonly AuditWriter $audit,
    ) {}

    public function execute(User $actor, Company $company, CompanyMember $member, string $companyRole): CompanyMember
    {
        return DB::transaction(function () use ($actor, $company, $member, $companyRole): CompanyMember {
            /** @var CompanyMember $locked */
            $locked = CompanyMember::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();
            $previous = $locked->company_role;

            $this->adminProtection->assertAdminMayBeReduced($locked, $companyRole);

            if ($previous !== $companyRole) {
                $locked->forceFill(['company_role' => $companyRole])->save();
                $this->audit->record('company_member_role_changed', $actor, 'company_member', (int) $locked->getKey(), [
                    'company_id' => (int) $company->getKey(),
                    'from' => $previous,
                    'to' => $companyRole,
                ]);
            }

            return $locked->refresh();
        });
    }
}
