<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;


final class SubmitCompanyVerificationRequest extends CompanyFormRequest
{
    public function rules(): array
    {
        return ['note' => ['sometimes', 'nullable', 'string', 'max:10000']];
    }
}
