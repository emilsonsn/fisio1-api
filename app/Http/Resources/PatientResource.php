<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'document' => $this->document, 'birth_date' => $this->birth_date?->toDateString(), 'phone' => $this->phone, 'email' => $this->email, 'notes' => $this->notes, 'has_photo' => (bool) $this->photo_path, 'clinical_records_count' => (int) ($this->assessments_count ?? 0) + (int) ($this->evolutions_count ?? 0), 'created_at' => $this->created_at?->toISOString()];
    }
}
