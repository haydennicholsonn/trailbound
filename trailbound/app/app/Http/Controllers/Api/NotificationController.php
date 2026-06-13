<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\ConversationParticipant;
use App\Models\Friend;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $friendIds = Friend::query()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('friend_id')
            ->push($user->id)
            ->unique()
            ->values();

        $pendingRequests = Friend::query()
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->with('user.profile')
            ->latest()
            ->get();

        $unreadConversations = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereHas('conversation.messages', function ($query) use ($user) {
                $query->where('user_id', '!=', $user->id);
            })
            ->with(['conversation.messages' => fn ($query) => $query->latest()->limit(1), 'conversation.participants.user.profile'])
            ->get()
            ->filter(function (ConversationParticipant $participant) {
                $last = $participant->conversation->messages->first();
                return $last && (! $participant->last_read_at || $last->created_at->gt($participant->last_read_at));
            })
            ->values();

        $activity = ActivityEvent::query()
            ->with('user.profile')
            ->whereIn('user_id', $friendIds)
            ->latest()
            ->limit(10)
            ->get();

        $items = collect()
            ->merge($pendingRequests->map(fn (Friend $friend) => [
                'id' => 'friend-' . $friend->id,
                'kind' => 'friend_request',
                'title' => 'New trail ally request',
                'body' => ($friend->user->profile?->display_name ?: $friend->user->name) . ' wants to connect.',
                'created_at' => $friend->created_at,
                'action' => 'Friends',
            ]))
            ->merge($unreadConversations->map(function (ConversationParticipant $participant) {
                $other = $participant->conversation->participants->firstWhere('user_id', '!=', $participant->user_id)?->user;
                $last = $participant->conversation->messages->first();
                return [
                    'id' => 'message-' . $participant->conversation_id,
                    'kind' => 'message',
                    'title' => 'Unread message',
                    'body' => ($other?->profile?->display_name ?: $other?->name ?: 'A friend') . ': ' . (string) str($last?->body ?? 'New message')->limit(72),
                    'created_at' => $last?->created_at,
                    'action' => 'Messages',
                ];
            }))
            ->merge($activity->map(fn (ActivityEvent $event) => [
                'id' => 'activity-' . $event->id,
                'kind' => $event->type,
                'title' => $this->titleFor($event->type),
                'body' => $this->bodyFor($event),
                'created_at' => $event->created_at,
                'action' => $event->type === 'region_discovered' ? 'Cape Town' : 'Feed',
            ]))
            ->sortByDesc('created_at')
            ->values()
            ->take(16);

        return response()->json([
            'unread_count' => $pendingRequests->count() + $unreadConversations->count(),
            'items' => $items,
        ]);
    }

    private function titleFor(string $type): string
    {
        return match ($type) {
            'region_discovered' => 'New region discovered',
            'run_logged', 'run_imported' => 'Run activity',
            'beacon_dropped' => 'Rally beacon',
            'friend_accepted' => 'Friend connected',
            default => 'Trailbound update',
        };
    }

    private function bodyFor(ActivityEvent $event): string
    {
        $name = $event->user->profile?->display_name ?: $event->user->name;
        $payload = $event->payload ?? [];

        return match ($event->type) {
            'region_discovered' => $name . ' discovered ' . ($payload['region_name'] ?? 'a region') . '.',
            'run_logged', 'run_imported' => $name . ' logged ' . ($payload['distance_km'] ?? '?') . 'km.',
            'beacon_dropped' => $name . ' dropped a beacon on the map.',
            'friend_accepted' => $name . ' connected with a trail ally.',
            default => $name . ' has new activity.',
        };
    }
}
