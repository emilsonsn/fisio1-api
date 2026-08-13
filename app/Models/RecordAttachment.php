<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size'])]
class RecordAttachment extends Model
{
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
