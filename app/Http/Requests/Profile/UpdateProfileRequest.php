<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome completo.',
            'name.max' => 'O nome deve ter no máximo 255 caracteres.',
            'photo.image' => 'A foto deve ser um arquivo de imagem.',
            'photo.max' => 'A foto deve ter no máximo 5 MB.',
            'photo.mimes' => 'A foto deve estar em JPG, PNG ou WEBP.',
        ];
    }
}
