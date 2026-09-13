<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domains\Identity\Enums\RoleCode;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `POST /admin/users/{user}/roles` (API_CONTRACT.md). `role_code` is the only
 * field and must be a member of the frozen approved catalogue (`RoleCode`,
 * FSD §3.1). No role is defaulted or inferred.
 */
final class AssignUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_code' => ['required', 'string', Rule::in(array_map(
                static fn (RoleCode $code): string => $code->value,
                RoleCode::cases(),
            ))],
        ];
    }

    public function roleCode(): string
    {
        return (string) $this->validated('role_code');
    }

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
