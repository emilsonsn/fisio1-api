<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['patient_id', 'professional_id', 'evolved_at', 'daily_complaint', 'pain_level', 'home_guidance_adherence', 'therapeutic_conduct', 'session_final_impression', 'observations'])]
class PatientEvolution extends Model
{
    protected function casts(): array
    {
        return ['evolved_at' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RecordAttachment::class, 'attachable');
    }
}
