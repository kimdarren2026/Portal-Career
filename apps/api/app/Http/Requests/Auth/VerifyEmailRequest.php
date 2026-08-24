<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

final class VerifyEmailRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:255']];
    }
}
