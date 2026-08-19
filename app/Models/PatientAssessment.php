<?php

namespace App\Models;

use App\Enums\ClinicalRecordStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['patient_id', 'professional_id', 'assessed_at', 'indication', 'birthplace', 'marital_status', 'gender', 'profession', 'address', 'chief_complaint', 'condition_history', 'life_habits', 'personal_family_history', 'previous_treatments', 'physical_examination', 'complementary_exams', 'physical_therapy_diagnosis', 'cbdf', 'planned_sessions', 'resources_methods_techniques', 'therapeutic_objectives', 'physical_therapy_prognosis', 'status', 'ai_transcript', 'ai_processed_at', 'confirmed_by', 'confirmed_at'])]
class PatientAssessment extends Model
{
    protected $attributes = ['status' => ClinicalRecordStatus::Completed->value];

    protected function casts(): array
    {
        return ['assessed_at' => 'date', 'status' => ClinicalRecordStatus::class, 'ai_processed_at' => 'datetime', 'confirmed_at' => 'datetime'];
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

    public function aiProcess(): MorphOne
    {
        return $this->morphOne(ClinicalAiProcess::class, 'processable')->latestOfMany();
    }
}
