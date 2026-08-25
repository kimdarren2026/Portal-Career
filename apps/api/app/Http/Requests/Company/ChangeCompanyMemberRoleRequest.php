<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Validation\Rule;

final class ChangeCompanyMemberRoleRequest extends CompanyFormRequest
{
    public function rules(): array
    {
        return ['company_role' => ['required', 'string', Rule::in(AddCompanyMemberRequest::ROLES)]];
    }
}
