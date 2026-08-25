<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Identity\Models\User;

class CreateInitialCompanyMembership
{
    public function execute(Company $company, User $user): CompanyMember
    {
        return CompanyMember::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'company_role' => 'COMPANY_ADMIN',
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }
}
