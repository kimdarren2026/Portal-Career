<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domains\Company\Models\CompanyMember;

/** Membership read model. No email or personal data beyond the identity key. */
final class CompanyMemberPresenter
{
    /** @return array<string, mixed> */
    public static function present(CompanyMember $member): array
    {
        return [
            'id' => (int) $member->getKey(),
            'user_id' => (int) $member->user_id,
            'company_role' => $member->company_role,
            'status' => $member->status,
            'joined_at' => $member->joined_at?->toIso8601String(),
            'revoked_at' => $member->revoked_at?->toIso8601String(),
        ];
    }
}
