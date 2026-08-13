<?php

namespace App\Http\Requests\PatientEvolution;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientEvolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['patient_id' => ['required', 'integer', 'exists:patients,id'], 'evolved_at' => ['required', 'date', 'before_or_equal:today'], 'daily_complaint' => ['nullable', 'string'], 'pain_level' => ['nullable', 'integer', 'between:0,10'], 'home_guidance_adherence' => ['nullable', 'string'], 'therapeutic_conduct' => ['nullable', 'string'], 'session_final_impression' => ['nullable', 'string'], 'observations' => ['nullable', 'string'], 'attachments' => ['nullable', 'array', 'max:10'], 'attachments.*' => ['file', 'max:10240']];
    }
}
