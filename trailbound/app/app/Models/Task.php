<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['region_id', 'title', 'description', 'unlock_rule', 'target_type', 'target_value', 'reward_xp', 'unlock_order'])]
class Task extends Model
{
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function states(): HasMany
    {
        return $this->hasMany(UserTask::class);
    }
}
