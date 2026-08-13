<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active', 'photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function clinicalRecords(): HasMany
    {
        return $this->hasMany(ClinicalRecord::class, 'professional_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(PatientAssessment::class, 'professional_id');
    }

    public function evolutions(): HasMany
    {
        return $this->hasMany(PatientEvolution::class, 'professional_id');
    }

    public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->accessGroups()
            ->whereHas('permissions', fn ($query) => $query->where('key', $permission))
            ->exists();
    }
}
