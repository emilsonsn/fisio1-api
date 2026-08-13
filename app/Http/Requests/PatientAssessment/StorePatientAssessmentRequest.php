<?php

namespace App\Http\Requests\PatientAssessment;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'], 'assessed_at' => ['required', 'date', 'before_or_equal:today'],
            'indication' => ['nullable', 'string', 'max:255'], 'birthplace' => ['nullable', 'string', 'max:255'], 'marital_status' => ['nullable', 'string', 'max:255'], 'gender' => ['nullable', 'string', 'max:100'], 'profession' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:255'],
            'chief_complaint' => ['nullable', 'string'], 'condition_history' => ['nullable', 'string'], 'life_habits' => ['nullable', 'string'], 'personal_family_history' => ['nullable', 'string'], 'previous_treatments' => ['nullable', 'string'], 'physical_examination' => ['nullable', 'string'], 'complementary_exams' => ['nullable', 'string'], 'physical_therapy_diagnosis' => ['nullable', 'string'], 'cbdf' => ['nullable', 'string'], 'planned_sessions' => ['nullable', 'integer', 'min:1', 'max:999'], 'resources_methods_techniques' => ['nullable', 'string'], 'therapeutic_objectives' => ['nullable', 'string'], 'physical_therapy_prognosis' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:10'], 'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
