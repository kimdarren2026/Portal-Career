<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Exceptions\CompanyMemberAlreadyActive;
use App\Domains\Company\Exceptions\CompanyMemberNotFound;
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
 *
 * The invitee must already hold an account. An unknown address is NOT SUPPORTED
 * at MVP (approved 25 August 2026, Part X item 10): no membership, no email, no
 * pending-invitation record. It raises the same not-found signal as a member id
 * outside this company, so the response cannot distinguish "no such account"
 * from "not visible to you" and the route cannot be used to enumerate accounts.
 */
final class AddCompanyMember
{
    public function __construct(private readonly AuditWriter $audit, private readonly OutboxWriter $outbox) {}

    public function execute(User $actor, Company $company, string $email, string $companyRole): CompanyMember
    {
        return DB::transaction(function () use ($actor, $company, $email, $companyRole): CompanyMember {
            Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();

            // INV-001: email_normalized is the sole identity key.
            $invitee = User::query()->where('email_normalized', EmailNormalizer::normalize($email))->first();
            if (! $invitee instanceof User) {
                throw new CompanyMemberNotFound();
            }

            $member = $this->attach($company, $invitee, $companyRole, $actor);

            // FR-COMP-004: the invitee still verifies their account through the
            // standard security mechanism. No temporary password is issued.
            $this->outbox->queue(
                recipient: $invitee->email,
                templateReference: 'company.member.invited',
                payload: ['company_id' => (int) $company->getKey(), 'company_role' => $companyRole],
                relatedObjectType: 'company',
                relatedObjectId: (int) $company->getKey(),
            );
            $this->audit->record('company_member_added', $actor, 'company_member', (int) $member->getKey(), [
                'company_id' => (int) $company->getKey(),
                'company_role' => $companyRole,
            ]);

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
