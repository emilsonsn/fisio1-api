<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['clinical_record_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class ClinicalAttachment extends Model
{
    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }
}
