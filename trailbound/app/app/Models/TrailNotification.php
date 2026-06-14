<?php

namespace App\Models;

use App\Support\Realtime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'actor_id', 'kind', 'title', 'body', 'action', 'payload', 'read_at'])]
class TrailNotification extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function sendTo(int $userId, string $kind, string $title, ?string $body = null, ?string $action = null, array $payload = [], ?int $actorId = null): ?self
    {
        $preferenceKey = match ($kind) {
            'friend_request', 'friend_accepted' => 'friend_requests',
            'message' => 'messages',
            'feed_reaction', 'feed_comment' => 'feed',
            'run_logged' => 'runs',
            'quest_complete' => 'quests',
            default => null,
        };

        if ($preferenceKey) {
            $preferences = User::query()
                ->with('profile')
                ->find($userId)
                ?->profile
                ?->notification_preferences ?? [];

            if (array_key_exists($preferenceKey, $preferences) && $preferences[$preferenceKey] === false) {
                return null;
            }
        }

        $notification = self::query()->create([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'action' => $action,
            'payload' => $payload,
        ]);

        Realtime::publish('notifications.updated', ['reason' => $kind, 'user_id' => $userId, 'actor_id' => $actorId]);

        return $notification;
    }
}
