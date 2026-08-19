<?php

namespace App\Models;

use App\Enums\ClinicalAiProcessStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['status', 'audio_disk', 'audio_path', 'original_name', 'mime_type', 'size', 'chunks_count', 'processed_chunks', 'transcript', 'error_message', 'started_at', 'completed_at', 'failed_at'])]
class ClinicalAiProcess extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ClinicalAiProcessStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function processable(): MorphTo
    {
        return $this->morphTo();
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ClinicalAiChunk::class)->orderBy('sequence');
    }
}
