<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document' => $this->document,
            'birth_date' => $this->birth_date?->toDateString(),
            'age' => $this->birth_date?->age,
            'phone' => $this->phone,
            'indication' => $this->indication,
            'birthplace' => $this->birthplace,
            'marital_status' => $this->marital_status,
            'gender' => $this->gender,
            'profession' => $this->profession,
            'address' => $this->address,
            'email' => $this->email,
            'notes' => $this->notes,
            'has_photo' => (bool) $this->photo_path,
            'is_deleted' => $this->trashed(),
            'clinical_records_count' => (int) ($this->assessments_count ?? 0) + (int) ($this->evolutions_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
