<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'event_label' => $this->event->label(),
            'event_group' => $this->event->group(),
            'user' => $this->user_id || $this->user_name ? [
                'id' => $this->user_id,
                'name' => $this->user?->name ?? $this->user_name,
                'email' => $this->user?->email ?? $this->user_email,
                'has_photo' => (bool) $this->user?->photo_path,
            ] : null,
            'auditable' => $this->auditable_type ? [
                'type' => class_basename($this->auditable_type),
                'id' => $this->auditable_id,
                'label' => $this->auditable_label,
            ] : null,
            'old_values' => $this->old_values ?? [],
            'new_values' => $this->new_values ?? [],
            'metadata' => $this->metadata ?? [],
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
