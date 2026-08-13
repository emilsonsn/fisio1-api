<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'document', 'birth_date', 'phone', 'email', 'notes', 'photo_path'])]
class Patient extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function clinicalRecords(): HasMany
    {
        return $this->hasMany(ClinicalRecord::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(PatientAssessment::class);
    }

    public function evolutions(): HasMany
    {
        return $this->hasMany(PatientEvolution::class);
    }
}
