<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserItem extends Model
{
    protected $fillable = [
        'user_id', 'item_id', 'quantity', 'acquired_at', 'acquired_from', 'equipped_at', 'market_listed_at',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'datetime',
            'equipped_at' => 'datetime',
            'market_listed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
