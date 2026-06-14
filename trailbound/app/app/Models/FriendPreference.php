<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'friend_id', 'is_favourite', 'muted_at'])]
class FriendPreference extends Model
{
    protected function casts(): array
    {
        return [
            'is_favourite' => 'boolean',
            'muted_at' => 'datetime',
        ];
    }
}
