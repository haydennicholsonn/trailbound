<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\FeedComment;
use App\Models\FeedReaction;
use App\Models\Friend;
use App\Models\TrailNotification;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $friendIds = Friend::query()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('friend_id')
            ->toArray();

        $friendIds[] = $user->id;

        $events = ActivityEvent::query()
            ->with(['user.profile', 'reactions', 'comments.user.profile'])
            ->whereIn('user_id', $friendIds)
            ->latest()
            ->limit(40)
            ->get()
            ->map(function (ActivityEvent $event) use ($user) {
                $reactionCounts = $event->reactions
                    ->groupBy('kind')
                    ->map(fn ($items) => $items->count());

                return [
                    'id' => $event->id,
                    'type' => $event->type,
                    'payload' => $event->payload,
                    'created_at' => $event->created_at,
                    'my_reaction' => $event->reactions->firstWhere('user_id', $user->id)?->kind,
                    'reactions' => [
                        'open_eye' => $reactionCounts->get('open_eye', 0),
                        'closed_eye' => $reactionCounts->get('closed_eye', 0),
                    ],
                    'comments_count' => $event->comments->count(),
                    'comments' => $event->comments->sortByDesc('created_at')->take(3)->reverse()->values()->map(fn (FeedComment $comment) => [
                        'id' => $comment->id,
                        'body' => $comment->body,
                        'created_at' => $comment->created_at,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->name,
                            'display_name' => $comment->user->profile?->display_name,
                            'avatar_path' => $comment->user->profile?->avatar_path,
                        ],
                    ]),
                    'user' => [
                        'id' => $event->user->id,
                        'name' => $event->user->name,
                        'display_name' => $event->user->profile?->display_name,
                        'avatar_path' => $event->user->profile?->avatar_path,
                    ],
                ];
            });

        return response()->json(['events' => $events]);
    }

    public function react(Request $request, int $eventId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'kind' => ['required', 'in:open_eye,closed_eye'],
        ]);

        $event = ActivityEvent::query()->findOrFail($eventId);
        $existing = FeedReaction::query()
            ->where('activity_event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing?->kind === $data['kind']) {
            $existing->delete();
        } else {
            FeedReaction::query()->updateOrCreate(
                ['activity_event_id' => $event->id, 'user_id' => $user->id],
                ['kind' => $data['kind']]
            );

            if ($event->user_id !== $user->id) {
                TrailNotification::sendTo(
                    $event->user_id,
                    'feed_reaction',
                    $data['kind'] === 'open_eye' ? 'Someone eyed your post' : 'Someone blinked at your post',
                    ($user->profile?->display_name ?: $user->name) . ' reacted to your activity.',
                    'Feed',
                    ['event_id' => $event->id, 'reaction' => $data['kind']],
                    $user->id
                );
            }
        }

        Realtime::publish('feed.updated', ['event_id' => $event->id]);

        return response()->json(['ok' => true]);
    }

    public function comment(Request $request, int $eventId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:600'],
        ]);

        $event = ActivityEvent::query()->findOrFail($eventId);
        $comment = FeedComment::query()->create([
            'activity_event_id' => $event->id,
            'user_id' => $user->id,
            'body' => $data['body'],
        ]);

        Realtime::publish('feed.updated', ['event_id' => $event->id]);

        if ($event->user_id !== $user->id) {
            TrailNotification::sendTo(
                $event->user_id,
                'feed_comment',
                'New comment',
                ($user->profile?->display_name ?: $user->name) . ' replied: ' . str($comment->body)->limit(72),
                'Feed',
                ['event_id' => $event->id, 'comment_id' => $comment->id],
                $user->id
            );
        }

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'display_name' => $user->profile?->display_name,
                    'avatar_path' => $user->profile?->avatar_path,
                ],
            ],
        ]);
    }
}
