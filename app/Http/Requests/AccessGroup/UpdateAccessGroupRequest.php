<?php

namespace App\Http\Requests\AccessGroup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('access_groups', 'name')->ignore($this->route('accessGroup'))
            ],
            'description' => [
                'nullable',
                'string', 
                'max:1000'
            ],
            'permission_ids' => [
                'sometimes',
                'array'
            ],
            'permission_ids.*' => [
                'integer',
                'distinct',
                'exists:permissions,id'
            ]
        ];
    }
}
