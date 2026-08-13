<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:8'], 'is_active' => ['sometimes', 'boolean'], 'photo' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'], 'access_group_ids' => ['present', 'array', 'min:1'], 'access_group_ids.*' => ['integer', 'distinct', 'exists:access_groups,id']];
    }
}
