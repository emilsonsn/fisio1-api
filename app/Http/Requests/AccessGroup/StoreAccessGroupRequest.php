<?php

namespace App\Http\Requests\AccessGroup;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', 'unique:access_groups,name'], 'description' => ['nullable', 'string', 'max:1000'], 'permission_ids' => ['present', 'array'], 'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id']];
    }
}
