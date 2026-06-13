<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'key', 'name', 'price_cents', 'billing_interval',
        'description', 'features', 'limits',
        'is_active', 'is_default', 'sort_order', 'stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
