<?php

namespace App\Models;

use App\Enums\ClinicalRecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['patient_id', 'professional_id', 'evolved_at', 'daily_complaint', 'pain_level', 'home_guidance_adherence', 'therapeutic_conduct', 'session_final_impression', 'observations', 'status', 'ai_transcript', 'ai_processed_at', 'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class PatientEvolution extends Model
{
    protected $attributes = ['status' => ClinicalRecordStatus::Completed->value];

    protected function casts(): array
    {
        return ['evolved_at' => 'date', 'status' => ClinicalRecordStatus::class, 'ai_processed_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RecordAttachment::class, 'attachable');
    }

    public function aiProcess(): MorphOne
    {
        return $this->morphOne(ClinicalAiProcess::class, 'processable')->latestOfMany();
    }
}
