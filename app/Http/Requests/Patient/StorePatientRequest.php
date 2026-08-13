<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'document' => ['required', 'string', 'max:32', 'unique:patients,document'], 'birth_date' => ['required', 'date', 'before:today'], 'phone' => ['required', 'string', 'max:32'], 'email' => ['nullable', 'email', 'max:255'], 'notes' => ['nullable', 'string'], 'photo' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp']];
    }
}
