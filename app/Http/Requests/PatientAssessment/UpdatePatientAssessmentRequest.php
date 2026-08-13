<?php

namespace App\Http\Requests\PatientAssessment;

class UpdatePatientAssessmentRequest extends StorePatientAssessmentRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['patient_id' => ['sometimes', 'integer', 'exists:patients,id'], 'assessed_at' => ['sometimes', 'date', 'before_or_equal:today']]);
    }
}
