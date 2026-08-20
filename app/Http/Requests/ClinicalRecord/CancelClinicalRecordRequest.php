<?php

namespace App\Http\Requests\ClinicalRecord;

use Illuminate\Foundation\Http\FormRequest;

class CancelClinicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
