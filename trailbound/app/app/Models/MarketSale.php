<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketSale extends Model
{
    protected $fillable = [
        'market_listing_id', 'item_id', 'seller_id', 'buyer_id', 'price_tears', 'sold_at',
    ];

    protected function casts(): array
    {
        return ['sold_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
