<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'is_active' => $this->is_active, 'has_photo' => (bool) $this->photo_path, 'access_groups' => AccessGroupResource::collection($this->whenLoaded('accessGroups')), 'permissions' => $this->whenLoaded('accessGroups', fn () => $this->accessGroups->flatMap->permissions->pluck('key')->unique()->values()), 'created_at' => $this->created_at?->toISOString()];
    }
}
