<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkillNode extends Model
{
    protected $fillable = [
        'user_id', 'skill_node_id', 'unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skillNode(): BelongsTo
    {
        return $this->belongsTo(SkillNode::class);
    }
}
