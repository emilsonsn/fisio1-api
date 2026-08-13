<?php

namespace App\Http\Requests\ClinicalRecord;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['patient_id' => ['required', 'integer', 'exists:patients,id'], 'type' => ['required', 'in:initial_assessment,evolution'], 'performed_at' => ['required', 'date', 'before_or_equal:today'], 'pain_level' => ['nullable', 'integer', 'between:0,10'], 'complaint' => ['nullable', 'string'], 'history' => ['nullable', 'string'], 'functional_limitations' => ['nullable', 'string'], 'treatment_objective' => ['nullable', 'string'], 'physical_assessment' => ['nullable', 'string'], 'conduct' => ['nullable', 'string'], 'next_steps' => ['nullable', 'string'], 'observations' => ['nullable', 'string'], 'attachments' => ['nullable', 'array', 'max:10'], 'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,png,jpg,jpeg']];
    }
}
