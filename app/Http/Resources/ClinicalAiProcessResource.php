<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalAiProcessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = (int) $this->chunks_count;
        $completed = (int) $this->processed_chunks;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'chunks_count' => $total,
            'processed_chunks' => $completed,
            'progress' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'error_message' => $this->error_message,
        ];
    }
}
