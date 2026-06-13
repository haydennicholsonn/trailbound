<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'region_id', 'status', 'progress', 'unlocked_at'])]
class UserRegionProgress extends Model
{
    protected function casts(): array
    {
        return ['unlocked_at' => 'datetime'];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
