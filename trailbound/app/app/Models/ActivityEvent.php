<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'type', 'payload'])]
class ActivityEvent extends Model
{
    protected $table = 'activity_events';

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(FeedReaction::class, 'activity_event_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'activity_event_id');
    }
}
