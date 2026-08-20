<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class VerifyPasswordRecoveryCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'code' => preg_replace('/\D/', '', (string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:'.config('password_recovery.code_length')],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail utilizado na recuperação.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'code.required' => 'Informe o código recebido por e-mail.',
            'code.digits' => 'O código deve possuir exatamente 6 dígitos.',
        ];
    }
}
