<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Exceptions\CompanyAlreadyExistsForUser;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Support\CompanyIdentifier;
use App\Domains\Company\Support\CompanyNameNormalizer;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

final class CreateCompany
{
    public function __construct(
        private readonly CreateInitialCompanyMembership $membership,
        private readonly AuditWriter $audit,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, array $attributes): Company
    {
        return DB::transaction(function () use ($actor, $attributes): Company {
            // Serialize creation attempts for the same creator; the schema has no creator uniqueness index.
            User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            if (Company::query()->where('created_by', $actor->getKey())->lockForUpdate()->exists()) {
                throw new CompanyAlreadyExistsForUser('COMPANY_ALREADY_EXISTS_FOR_USER');
            }

            $company = new Company();
            $company->forceFill([
                ...$attributes,
                'normalized_name' => CompanyNameNormalizer::normalize((string) $attributes['name']),
                // PD-2: assigned once at creation, stable thereafter — a later
                // name edit never regenerates it (no update path touches it).
                'slug' => CompanyIdentifier::slug((string) $attributes['name']),
                'verification_status' => CompanyStatus::Draft,
                'created_by' => $actor->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->save();

            // Deliberately the only implicit role: closed D-1 applies to the creator only.
            $this->membership->execute($company, $actor);
            $this->audit->record('company_created', $actor, 'company', (int) $company->getKey());

            return $company->refresh();
        });
    }
}
