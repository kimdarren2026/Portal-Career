<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;


final class ReviewCompanyRequest extends CompanyFormRequest
{
    public function rules(): array
    {
        return [
            'reason_category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recruiter_visible_note' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
