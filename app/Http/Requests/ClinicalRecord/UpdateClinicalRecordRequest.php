<?php

namespace App\Http\Requests\ClinicalRecord;

class UpdateClinicalRecordRequest extends StoreClinicalRecordRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['patient_id' => ['sometimes', 'integer', 'exists:patients,id'], 'type' => ['sometimes', 'in:initial_assessment,evolution'], 'performed_at' => ['sometimes', 'date', 'before_or_equal:today']]);
    }
}
