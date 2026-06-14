<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LootBoxOpen extends Model
{
    protected $fillable = [
        'user_id', 'box_key', 'server_seed_hash', 'server_seed', 'client_seed',
        'nonce', 'roll_hash', 'roll', 'reward_type', 'reward_label',
        'reward_payload', 'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_payload' => 'array',
            'opened_at' => 'datetime',
            'roll' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
