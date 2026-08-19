<?php

namespace App\Models;

use App\Enums\ClinicalAiChunkStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sequence', 'status', 'disk', 'path', 'mime_type', 'attempts', 'transcript', 'error_message'])]
class ClinicalAiChunk extends Model
{
    protected function casts(): array
    {
        return ['status' => ClinicalAiChunkStatus::class];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ClinicalAiProcess::class, 'clinical_ai_process_id');
    }
}
