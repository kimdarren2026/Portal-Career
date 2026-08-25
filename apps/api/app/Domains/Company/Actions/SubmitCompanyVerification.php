<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Enums\CompanyReviewAction;
use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Exceptions\CompanyInvalidTransition;
use App\Domains\Company\Exceptions\CompanyProfileIncomplete;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Company\Support\CompanyVerificationNotifier;
use Illuminate\Support\Facades\DB;

final class SubmitCompanyVerification
{
    public function __construct(private readonly AuditWriter $audit, private readonly CompanyVerificationNotifier $notifier) {}

    public function execute(User $actor, Company $company): Company
    {
        return DB::transaction(function () use ($actor, $company): Company {
            /** @var Company $locked */
            $locked = Company::query()->with('documents')->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->verification_status;
            if (! in_array($from, [CompanyStatus::Draft, CompanyStatus::RevisionRequired], true)) {
                throw new CompanyInvalidTransition('COMPANY_INVALID_TRANSITION');
            }
            $missing = [];
            foreach (['organization_type_id', 'industry_id', 'official_email', 'address', 'province_geographic_area_id', 'city_geographic_area_id'] as $field) {
                if ($locked->{$field} === null || $locked->{$field} === '') { $missing[] = $field; }
            }
            if (! $locked->documents()->whereNull('superseded_at')->exists()) { $missing[] = 'company_documents'; }
            if ($missing !== []) { throw new CompanyProfileIncomplete($missing); }

            $locked->verification_status = CompanyStatus::PendingVerification;
            $locked->updated_at = now();
            $locked->save();
            $locked->documents()->whereNull('first_submitted_at')->whereNull('superseded_at')->update(['first_submitted_at' => now(), 'updated_at' => now()]);
            $this->review($locked, $actor, CompanyReviewAction::Submit, $from, CompanyStatus::PendingVerification);
            $this->audit->record('company_submitted', $actor, 'company', (int) $locked->getKey());
            $this->notifier->queue($locked, 'SUBMITTED');
            return $locked->refresh();
        });
    }

    private function review(Company $company, User $actor, CompanyReviewAction $action, CompanyStatus $from, CompanyStatus $to): void
    {
        $company->verificationReviews()->create([
            'reviewer_user_id' => $actor->getKey(), 'action' => $action->value,
            'from_status' => $from->value, 'to_status' => $to->value, 'reviewed_at' => now(),
        ]);
    }
}
