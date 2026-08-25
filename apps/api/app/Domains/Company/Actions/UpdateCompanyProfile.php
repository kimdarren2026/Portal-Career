<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Exceptions\CompanyInvalidTransition;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Support\CompanyNameNormalizer;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

final class UpdateCompanyProfile
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Company $company, array $attributes): Company
    {
        return DB::transaction(function () use ($actor, $company, $attributes): Company {
            /** @var Company $locked */
            $locked = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->verification_status, [CompanyStatus::Draft, CompanyStatus::RevisionRequired], true)) {
                throw new CompanyInvalidTransition('COMPANY_INVALID_TRANSITION');
            }
            if (array_key_exists('name', $attributes)) {
                $attributes['normalized_name'] = CompanyNameNormalizer::normalize((string) $attributes['name']);
            }
            $locked->fill($attributes);
            $changed = array_keys($locked->getDirty());
            if ($changed !== []) {
                $locked->updated_at = now();
                $locked->save();
                $this->audit->record('company_updated', $actor, 'company', (int) $locked->getKey(), ['fields' => $changed]);
            }
            return $locked->refresh();
        });
    }
}
