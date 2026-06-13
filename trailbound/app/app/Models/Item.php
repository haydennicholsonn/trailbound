<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'key', 'name', 'icon', 'rarity', 'description',
        'type', 'category', 'value_tears', 'stackable',
        'max_stack', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stackable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function shopListings(): HasMany
    {
        return $this->hasMany(ShopItem::class);
    }
}
