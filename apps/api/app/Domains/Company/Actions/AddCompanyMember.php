<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Exceptions\CompanyMemberAlreadyActive;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * POST /companies/{company}/members (FR-COMP-004).
 *
 * `company_role` is always chosen explicitly by the caller. Closed D-1 gives an
 * implicit role to the company creator alone; every later member is explicit.
 * No temporary password is issued — an invitee reaches the company through the
 * standard account and email-verification mechanism.
 *
 * `company_members.user_id` is NOT NULL and no invitation entity exists in the
 * frozen schema, so an address with no account yet gets the invitation email
 * and no membership row — the contract's documented `202` outcome. A row is
 * created only once that account exists.
 */
final class AddCompanyMember
{
    public function __construct(private readonly AuditWriter $audit, private readonly OutboxWriter $outbox) {}

    /** @return CompanyMember|null Null when only the invitation email was issued. */
    public function execute(User $actor, Company $company, string $email, string $companyRole): ?CompanyMember
    {
        return DB::transaction(function () use ($actor, $company, $email, $companyRole): ?CompanyMember {
            Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();

            // INV-001: email_normalized is the sole identity key.
            $invitee = User::query()->where('email_normalized', EmailNormalizer::normalize($email))->first();

            $member = $invitee === null ? null : $this->attach($company, $invitee, $companyRole, $actor);

            $this->outbox->queue(
                recipient: $email,
                templateReference: 'company.member.invited',
                payload: ['company_id' => (int) $company->getKey(), 'company_role' => $companyRole],
                relatedObjectType: 'company',
                relatedObjectId: (int) $company->getKey(),
            );

            if ($member !== null) {
                $this->audit->record('company_member_added', $actor, 'company_member', (int) $member->getKey(), [
                    'company_id' => (int) $company->getKey(),
                    'company_role' => $companyRole,
                ]);
            }

            return $member;
        });
    }

    private function attach(Company $company, User $invitee, string $companyRole, User $actor): CompanyMember
    {
        try {
            return CompanyMember::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $invitee->getKey(),
                'company_role' => $companyRole,
                'status' => 'ACTIVE',
                'joined_at' => now(),
                'invited_by' => $actor->getKey(),
            ]);
        } catch (QueryException $exception) {
            // uq_company_members_company_user_active (INV-017). SQLSTATE 23505
            // must surface as 409, never as a 500.
            if ($exception->getCode() === '23505') {
                throw new CompanyMemberAlreadyActive('MEMBER_ALREADY_ACTIVE');
            }

            throw $exception;
        }
    }
}
