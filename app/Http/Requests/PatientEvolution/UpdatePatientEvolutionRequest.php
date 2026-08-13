<?php

namespace App\Http\Requests\PatientEvolution;

class UpdatePatientEvolutionRequest extends StorePatientEvolutionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['patient_id' => ['sometimes', 'integer', 'exists:patients,id'], 'evolved_at' => ['sometimes', 'date', 'before_or_equal:today']]);
    }
}
