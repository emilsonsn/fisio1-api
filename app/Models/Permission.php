<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['key', 'name', 'module', 'description'])]
class Permission extends Model
{
    public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class);
    }
}
