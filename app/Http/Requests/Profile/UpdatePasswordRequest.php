<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Informe sua senha atual.',
            'current_password.current_password' => 'A senha atual informada está incorreta.',
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A nova senha deve possuir pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da nova senha não coincide.',
        ];
    }
}
