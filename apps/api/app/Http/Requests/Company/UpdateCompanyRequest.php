<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Validation\Rule;

final class UpdateCompanyRequest extends CompanyFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:200'],
            'organization_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('organization_types', 'id')->where('active', true)],
            'industry_id' => ['sometimes', 'nullable', 'integer', Rule::exists('industries', 'id')->where('active', true)],
            'website' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'official_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'official_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string'],
            'province_geographic_area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('geographic_areas', 'id')->where('active', true)],
            'city_geographic_area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('geographic_areas', 'id')->where('active', true)],
            'legal_identifier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_storage_reference' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }
}
