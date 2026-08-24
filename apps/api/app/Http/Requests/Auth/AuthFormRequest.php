<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domains\Identity\Support\PasswordPolicy;
use App\Http\Responses\ContractResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class AuthFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        $passwordFields = ['password', 'password_confirmation'];
        $code = array_intersect($passwordFields, array_keys($errors)) !== []
            ? 'AUTH_PASSWORD_POLICY'
            : 'VALIDATION_FAILED';
        $message = $code === 'AUTH_PASSWORD_POLICY'
            ? 'Password belum memenuhi kebijakan keamanan.'
            : 'Data yang dikirim tidak valid.';

        throw new HttpResponseException(ContractResponse::error(
            $this,
            $code,
            422,
            $message,
            ['fields' => $errors],
        ));
    }

    /** @return list<\Closure> */
    protected function passwordPolicyAfterValidation(string $passwordField = 'password'): array
    {
        return [function (Validator $validator) use ($passwordField): void {
            $password = (string) $this->input($passwordField, '');
            $email = (string) $this->input('email', '');
            if ($password === '') {
                return;
            }

            if (! PasswordPolicy::passes($password, $email)) {
                $validator->errors()->add($passwordField, 'Password harus minimal 8 karakter, memuat huruf besar, huruf kecil, angka, dan tidak sama dengan email.');
            }
        }];
    }
}
