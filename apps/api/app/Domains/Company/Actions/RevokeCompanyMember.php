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
 * DELETE /companies/{company}/members/{member} — revoke, or leave when the
 * actor revokes their own membership (FR-COMP-004).
 *
 * Sets `revoked_at`; the row is never deleted, because FR-COMP-004 requires
 * that removing a membership does not destroy audit or history. Revoking the
 * last active COMPANY_ADMIN is refused (closed D-1).
 */
final class RevokeCompanyMember
{
    public function __construct(
        private readonly CompanyAdminProtection $adminProtection,
        private readonly AuditWriter $audit,
    ) {}

    public function execute(User $actor, Company $company, CompanyMember $member): void
    {
        DB::transaction(function () use ($actor, $company, $member): void {
            /** @var CompanyMember $locked */
            $locked = CompanyMember::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isActive()) {
                return; // Naturally idempotent: an already-revoked membership grants nothing.
            }

            $this->adminProtection->assertAdminMayBeReduced($locked, deactivate: true);

            // Only `revoked_at` is written. `company_members.status` is a
            // vocabulary-pending varchar (DATABASE_SCHEMA.md), so no new value
            // is invented here; `revoked_at` is what scopeActive() and
            // uq_company_members_company_user_active both key on.
            $locked->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('company_member_revoked', $actor, 'company_member', (int) $locked->getKey(), [
                'company_id' => (int) $company->getKey(),
                'self_initiated' => (int) $locked->user_id === (int) $actor->getKey(),
            ]);
        });
    }
}
