<?php

namespace App\Http\Requests\ClinicalAi;

use Illuminate\Foundation\Http\FormRequest;

class ProcessClinicalAudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'type' => ['required', 'in:initial_assessment,evolution'],
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'audio' => ['required', 'file', 'max:102400']
        ];
    }
}
