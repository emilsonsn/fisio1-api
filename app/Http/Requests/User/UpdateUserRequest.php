<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))], 'password' => ['nullable', 'string', 'min:8'], 'is_active' => ['sometimes', 'boolean'], 'photo' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'], 'access_group_ids' => ['sometimes', 'array', 'min:1'], 'access_group_ids.*' => ['integer', 'distinct', 'exists:access_groups,id']];
    }
}
