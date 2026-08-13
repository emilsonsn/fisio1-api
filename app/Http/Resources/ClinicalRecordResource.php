<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'patient_id' => $this->patient_id, 'professional_id' => $this->professional_id, 'type' => $this->type, 'performed_at' => $this->performed_at?->toDateString(), 'pain_level' => $this->pain_level, 'complaint' => $this->complaint, 'history' => $this->history, 'functional_limitations' => $this->functional_limitations, 'treatment_objective' => $this->treatment_objective, 'physical_assessment' => $this->physical_assessment, 'conduct' => $this->conduct, 'next_steps' => $this->next_steps, 'observations' => $this->observations, 'reviewed_at' => $this->reviewed_at?->toISOString(), 'patient' => new PatientResource($this->whenLoaded('patient')), 'professional' => new UserResource($this->whenLoaded('professional')), 'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => ['id' => $attachment->id, 'name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'size' => $attachment->size, 'download_url' => route('attachments.download', $attachment)])), 'created_at' => $this->created_at?->toISOString()];
    }
}
