<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketListing extends Model
{
    protected $fillable = [
        'user_item_id', 'item_id', 'seller_id', 'buyer_id',
        'price_tears', 'status', 'sold_at',
    ];

    protected function casts(): array
    {
        return ['sold_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function userItem(): BelongsTo
    {
        return $this->belongsTo(UserItem::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
