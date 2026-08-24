<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;

final class RegisterCandidateRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'candidate_type' => ['required', 'string', 'in:EXTERNAL,FINAL_YEAR_STUDENT,ALUMNI'],
            'accepted_terms' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(...$this->passwordPolicyAfterValidation());
    }
}
