<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientEvolutionResource extends JsonResource
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
            'evolved_at' => $this->evolved_at?->toDateString(),
            'daily_complaint' => $this->daily_complaint,
            'pain_level' => $this->pain_level,
            'home_guidance_adherence' => $this->home_guidance_adherence,
            'therapeutic_conduct' => $this->therapeutic_conduct,
            'session_final_impression' => $this->session_final_impression,
            'observations' => $this->observations,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'professional' => new UserResource($this->whenLoaded('professional')),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'download_url' => route('record-attachments.download', $attachment),
            ])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
