<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Enums\CompanyReviewAction;
use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Exceptions\CompanyInvalidTransition;
use App\Domains\Company\Exceptions\ReviewReasonRequired;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Company\Support\CompanyVerificationNotifier;
use Illuminate\Support\Facades\DB;

final class ReviewCompanyVerification
{
    public function __construct(private readonly AuditWriter $audit, private readonly CompanyVerificationNotifier $notifier) {}

    /** @param array<string, mixed> $details */
    public function execute(User $reviewer, Company $company, CompanyReviewAction $action, array $details = []): Company
    {
        return DB::transaction(function () use ($reviewer, $company, $action, $details): Company {
            /** @var Company $locked */
            $locked = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            [$from, $to] = $this->transition($locked->verification_status, $action);
            if (in_array($action, [CompanyReviewAction::RequestRevision, CompanyReviewAction::Reject, CompanyReviewAction::Suspend], true)
                && (trim((string) ($details['reason_category'] ?? '')) === '' || trim((string) ($details['recruiter_visible_note'] ?? '')) === '')) {
                throw new ReviewReasonRequired();
            }
            $locked->verification_status = $to;
            if ($to === CompanyStatus::Verified) { $locked->verified_at = now(); $locked->suspended_at = null; }
            if ($to === CompanyStatus::Suspended) { $locked->suspended_at = now(); }
            $locked->updated_at = now();
            $locked->save();
            $locked->verificationReviews()->create([
                'reviewer_user_id' => $reviewer->getKey(), 'action' => $action->value,
                'from_status' => $from->value, 'to_status' => $to->value,
                'reason_category' => $details['reason_category'] ?? null,
                'recruiter_visible_note' => $details['recruiter_visible_note'] ?? null,
                'internal_note' => $details['internal_note'] ?? null, 'reviewed_at' => now(),
            ]);
            $this->audit->record($this->auditAction($action), $reviewer, 'company', (int) $locked->getKey());
            $this->notifier->queue($locked, $action->value);
            return $locked->refresh();
        });
    }

    /** @return array{CompanyStatus, CompanyStatus} */
    private function transition(CompanyStatus $from, CompanyReviewAction $action): array
    {
        $to = match ($action) {
            CompanyReviewAction::Verify => CompanyStatus::Verified,
            CompanyReviewAction::RequestRevision => CompanyStatus::RevisionRequired,
            CompanyReviewAction::Reject => CompanyStatus::Rejected,
            CompanyReviewAction::Suspend => CompanyStatus::Suspended,
            CompanyReviewAction::Restore => CompanyStatus::Verified,
            CompanyReviewAction::Submit => CompanyStatus::PendingVerification,
        };
        $allowed = match ($action) {
            CompanyReviewAction::Verify, CompanyReviewAction::RequestRevision, CompanyReviewAction::Reject => $from === CompanyStatus::PendingVerification,
            CompanyReviewAction::Suspend => $from === CompanyStatus::Verified,
            CompanyReviewAction::Restore => $from === CompanyStatus::Suspended,
            CompanyReviewAction::Submit => false,
        };
        if (! $allowed) { throw new CompanyInvalidTransition('COMPANY_INVALID_TRANSITION'); }
        return [$from, $to];
    }

    private function auditAction(CompanyReviewAction $action): string
    {
        return match ($action) {
            CompanyReviewAction::Verify => 'company_verified',
            CompanyReviewAction::RequestRevision => 'company_revision_requested',
            CompanyReviewAction::Reject => 'company_rejected',
            CompanyReviewAction::Suspend => 'company_suspended',
            CompanyReviewAction::Restore => 'company_restored',
            CompanyReviewAction::Submit => 'company_submitted',
        };
    }
}
