<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use App\Domains\Company\Exceptions\LastCompanyAdmin;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use Illuminate\Support\Facades\DB;

/** Serializes admin-reducing membership mutations on the company aggregate. */
final class CompanyAdminProtection
{
    public function assertAdminMayBeReduced(CompanyMember $membership, ?string $replacementRole = null, bool $deactivate = false): void
    {
        if (! $membership->isActive() || $membership->company_role !== 'COMPANY_ADMIN') {
            return;
        }
        if (! $deactivate && $replacementRole === 'COMPANY_ADMIN') {
            return;
        }

        // The company lock makes two concurrent last-admin reductions serialize.
        Company::query()->whereKey($membership->company_id)->lockForUpdate()->firstOrFail();
        $remaining = CompanyMember::query()->active()
            ->where('company_id', $membership->company_id)
            ->where('company_role', 'COMPANY_ADMIN')
            ->where('id', '<>', $membership->getKey())
            ->count();
        if ($remaining < 1) {
            throw new LastCompanyAdmin('MEMBER_LAST_ADMIN');
        }
    }
}
