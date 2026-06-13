<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['connection_id', 'object_type', 'object_id', 'aspect_type', 'owner_id', 'event_time', 'updates'])]
class StravaWebhookEvent extends Model
{
    protected $table = 'strava_webhook_events';

    protected function casts(): array
    {
        return [
            'event_time' => 'integer',
            'updates' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
