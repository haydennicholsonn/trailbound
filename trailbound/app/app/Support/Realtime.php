<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Throwable;

class Realtime
{
    public static function publish(string $type, array $payload = []): void
    {
        try {
            Redis::publish('trailbound:realtime', json_encode([
                'type' => $type,
                'payload' => $payload,
                'at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            // Realtime is nice-to-have; core writes must never fail because websocket fanout is unavailable.
        }
    }
}
