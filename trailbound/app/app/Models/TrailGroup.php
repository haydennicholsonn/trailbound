<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrailGroup extends Model
{
    protected $fillable = ['owner_id', 'name', 'slug', 'description', 'visibility', 'icon', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TrailGroupMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TrailGroupMessage::class);
    }
}
