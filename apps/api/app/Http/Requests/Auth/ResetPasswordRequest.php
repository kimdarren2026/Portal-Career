<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;

final class ResetPasswordRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(...$this->passwordPolicyAfterValidation());
    }
}
