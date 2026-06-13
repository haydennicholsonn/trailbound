<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillNode extends Model
{
    protected $fillable = [
        'key', 'name', 'icon', 'description', 'branch', 'tier', 'position',
        'requirement_type', 'requirement_value', 'cost_tears',
        'effect', 'effect_stat', 'effect_value', 'prerequisite_keys',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'prerequisite_keys' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
