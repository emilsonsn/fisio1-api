<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['patient_id', 'professional_id', 'type', 'performed_at', 'transcript', 'fields', 'processed_at', 'confirmed_at'])]
class ClinicalAiDraft extends Model
{
    protected function casts(): array
    {
        return ['performed_at' => 'date', 'fields' => 'array', 'processed_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
