<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'professional_id' => $this->professional_id,
            'status' => $this->status->value,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'ai_process' => new ClinicalAiProcessResource($this->whenLoaded('aiProcess')),
            'assessed_at' => $this->assessed_at?->toDateString(),
            'indication' => $this->indication,
            'birthplace' => $this->birthplace,
            'marital_status' => $this->marital_status,
            'gender' => $this->gender,
            'profession' => $this->profession,
            'address' => $this->address,
            'chief_complaint' => $this->chief_complaint,
            'condition_history' => $this->condition_history,
            'life_habits' => $this->life_habits,
            'personal_family_history' => $this->personal_family_history,
            'previous_treatments' => $this->previous_treatments,
            'physical_examination' => $this->physical_examination,
            'complementary_exams' => $this->complementary_exams,
            'physical_therapy_diagnosis' => $this->physical_therapy_diagnosis,
            'cbdf' => $this->cbdf,
            'planned_sessions' => $this->planned_sessions,
            'resources_methods_techniques' => $this->resources_methods_techniques,
            'therapeutic_objectives' => $this->therapeutic_objectives,
            'physical_therapy_prognosis' => $this->physical_therapy_prognosis,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'professional' => new UserResource($this->whenLoaded('professional')),
            'attachments' => $this->attachments(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function attachments(): mixed
    {
        return $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'download_url' => route('record-attachments.download', $attachment),
        ]));
    }
}
