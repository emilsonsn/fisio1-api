<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['patient_id', 'professional_id', 'type', 'performed_at', 'pain_level', 'complaint', 'history', 'functional_limitations', 'treatment_objective', 'physical_assessment', 'conduct', 'next_steps', 'observations', 'reviewed_at'])]
class ClinicalRecord extends Model
{
    protected function casts(): array
    {
        return ['performed_at' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClinicalAttachment::class);
    }
}
