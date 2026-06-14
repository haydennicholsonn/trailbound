<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrailGroupMessage extends Model
{
    protected $fillable = ['trail_group_id', 'user_id', 'body'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TrailGroup::class, 'trail_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
