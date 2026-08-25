<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Company transport base. Authorization is a Policy decision made in the
 * controller against the resolved company, never a role guess in authorize().
 */
abstract class CompanyFormRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ContractResponse::error(
            $this,
            'VALIDATION_FAILED',
            422,
            'Data yang dikirim tidak valid.',
            ['fields' => $validator->errors()->toArray()],
        ));
    }
}
