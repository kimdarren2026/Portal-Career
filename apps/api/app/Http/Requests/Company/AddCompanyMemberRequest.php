<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Validation\Rule;

final class AddCompanyMemberRequest extends CompanyFormRequest
{
    /** Closed D-1: every member after the creator selects a role explicitly. */
    public const ROLES = ['COMPANY_ADMIN', 'COMPANY_RECRUITER'];

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'company_role' => ['required', 'string', Rule::in(self::ROLES)],
        ];
    }
}
