<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;

final class RegisterRecruiterRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'accepted_terms' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(...$this->passwordPolicyAfterValidation());
    }
}
