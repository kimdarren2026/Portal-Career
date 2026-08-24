<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

final class LoginRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
