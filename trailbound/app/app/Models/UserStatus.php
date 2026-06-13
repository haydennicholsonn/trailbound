<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'status_text', 'mood'])]
class UserStatus extends Model
{
    protected $table = 'user_statuses';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
