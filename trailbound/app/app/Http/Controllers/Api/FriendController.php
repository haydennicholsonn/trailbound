<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Friend;
use App\Models\FriendNickname;
use App\Models\FriendPreference;
use App\Models\TrailNotification;
use App\Models\User;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $friends = Friend::query()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->with('friend.profile')
            ->get();

        $pending = Friend::query()
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->with('user.profile')
            ->get();

        $nicknames = FriendNickname::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('friend_id');
        $preferences = FriendPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('friend_id');

        $result = $friends->map(function (Friend $f) use ($nicknames, $preferences) {
            $friendUser = $f->friend;
            $nickname = $nicknames[$friendUser->id]->nickname ?? null;
            $preference = $preferences[$friendUser->id] ?? null;
            return [
                'id' => $friendUser->id,
                'name' => $friendUser->name,
                'display_name' => $friendUser->profile?->display_name,
                'nickname' => $nickname,
                'runner_type' => $friendUser->profile?->runner_type,
                'level' => $friendUser->profile?->level,
                'avatar_path' => $friendUser->profile?->avatar_path,
                'home_area' => $friendUser->profile?->home_area,
                'friends_since' => $f->created_at,
                'is_favourite' => (bool) ($preference?->is_favourite ?? false),
                'muted_at' => $preference?->muted_at,
            ];
        });

        $pendingSent = Friend::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->with('friend.profile')
            ->get()
            ->map(fn (Friend $f) => [
                'id' => $f->friend->id,
                'name' => $f->friend->name,
                'display_name' => $f->friend->profile?->display_name,
            ]);

        return response()->json([
            'friends' => $result,
            'pending_received' => $pending->map(function (Friend $f) {
                return [
                    'id' => $f->user->id,
                    'name' => $f->user->name,
                    'display_name' => $f->user->profile?->display_name,
                    'request_id' => $f->id,
                ];
            }),
            'pending_sent' => $pendingSent,
        ]);
    }

    public function request(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'identifier' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'string', 'max:180'],
        ]);

        $identifier = trim((string) ($data['identifier'] ?? $data['email'] ?? ''));
        if ($identifier === '') {
            return response()->json(['message' => 'Enter an email, username, or friend code.'], 422);
        }

        $needle = strtolower($identifier);
        $target = User::query()
            ->whereRaw('LOWER(email) = ?', [$needle])
            ->orWhereRaw('LOWER(name) = ?', [$needle])
            ->orWhereHas('profile', function ($query) use ($needle) {
                $query->whereRaw('LOWER(friend_code) = ?', [$needle])
                    ->orWhereRaw('LOWER(display_name) = ?', [$needle]);
            })
            ->first();

        if (!$target) {
            return response()->json(['message' => 'No runner found with that email, username, or friend code.'], 404);
        }

        if ($target->id === $user->id) {
            return response()->json(['message' => 'You cannot friend yourself.'], 400);
        }

        $existing = Friend::query()
            ->where(function ($q) use ($user, $target) {
                $q->where('user_id', $user->id)->where('friend_id', $target->id);
            })
            ->orWhere(function ($q) use ($user, $target) {
                $q->where('user_id', $target->id)->where('friend_id', $user->id);
            })
            ->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                return response()->json(['message' => 'You are already friends.'], 400);
            }
            if ($existing->status === 'pending') {
                return response()->json(['message' => 'A friend request is already pending.'], 400);
            }
            if ($existing->status === 'blocked') {
                return response()->json(['message' => 'Cannot send friend request.'], 400);
            }
        }

        $friend = Friend::query()->create([
            'user_id' => $user->id,
            'friend_id' => $target->id,
            'status' => 'pending',
        ]);

        ActivityEvent::query()->create([
            'user_id' => $user->id,
            'type' => 'friend_request_sent',
            'payload' => ['to_user_id' => $target->id],
        ]);

        Realtime::publish('notifications.updated', ['reason' => 'friend_request', 'user_id' => $user->id, 'target_user_id' => $target->id]);

        TrailNotification::sendTo(
            $target->id,
            'friend_request',
            'New trail ally request',
            ($user->profile?->display_name ?: $user->name) . ' wants to connect.',
            'Friends',
            ['request_id' => $friend->id],
            $user->id
        );

        return response()->json(['message' => 'Friend request sent.']);
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'friend_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $deleted = Friend::query()
            ->where('user_id', $user->id)
            ->where('friend_id', $data['friend_id'])
            ->where('status', 'pending')
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Pending request not found.'], 404);
        }

        Realtime::publish('social.updated', ['reason' => 'friend_request_cancelled', 'user_id' => $user->id, 'friend_id' => $data['friend_id']]);

        return response()->json(['message' => 'Friend request cancelled.']);
    }

    public function accept(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'request_id' => ['required', 'integer', 'exists:friends,id'],
        ]);

        $friendRequest = Friend::query()
            ->where('id', $data['request_id'])
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$friendRequest) {
            return response()->json(['message' => 'Friend request not found.'], 404);
        }

        $friendRequest->update(['status' => 'accepted']);

        Friend::query()->firstOrCreate(
            ['user_id' => $user->id, 'friend_id' => $friendRequest->user_id],
            ['status' => 'accepted']
        );

        ActivityEvent::query()->create([
            'user_id' => $user->id,
            'type' => 'friend_accepted',
            'payload' => ['friend_id' => $friendRequest->user_id],
        ]);

        ActivityEvent::query()->create([
            'user_id' => $friendRequest->user_id,
            'type' => 'friend_accepted',
            'payload' => ['friend_id' => $user->id],
        ]);

        Realtime::publish('social.updated', ['reason' => 'friend_accepted', 'user_id' => $user->id, 'friend_id' => $friendRequest->user_id]);
        Realtime::publish('notifications.updated', ['reason' => 'friend_accepted', 'user_id' => $user->id]);

        TrailNotification::sendTo(
            $friendRequest->user_id,
            'friend_accepted',
            'Trail ally connected',
            ($user->profile?->display_name ?: $user->name) . ' accepted your friend request.',
            'Social',
            ['friend_id' => $user->id],
            $user->id
        );

        return response()->json(['message' => 'Friend request accepted.']);
    }

    public function reject(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'request_id' => ['required', 'integer', 'exists:friends,id'],
        ]);

        $friendRequest = Friend::query()
            ->where('id', $data['request_id'])
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$friendRequest) {
            return response()->json(['message' => 'Friend request not found.'], 404);
        }

        $friendRequest->delete();

        return response()->json(['message' => 'Friend request rejected.']);
    }

    public function remove(Request $request, int $friendId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Friend::query()
            ->where(function ($q) use ($user, $friendId) {
                $q->where('user_id', $user->id)->where('friend_id', $friendId);
            })
            ->orWhere(function ($q) use ($user, $friendId) {
                $q->where('user_id', $friendId)->where('friend_id', $user->id);
            })
            ->delete();

        FriendNickname::query()
            ->where(function ($q) use ($user, $friendId) {
                $q->where('user_id', $user->id)->where('friend_id', $friendId);
            })
            ->orWhere(function ($q) use ($user, $friendId) {
                $q->where('user_id', $friendId)->where('friend_id', $user->id);
            })
            ->delete();

        Realtime::publish('social.updated', ['reason' => 'friend_removed', 'user_id' => $user->id, 'friend_id' => $friendId]);

        return response()->json(['message' => 'Friend removed.']);
    }

    public function updateNickname(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'friend_id' => ['required', 'integer', 'exists:users,id'],
            'nickname' => ['required', 'string', 'max:80'],
        ]);

        FriendNickname::query()->updateOrCreate(
            ['user_id' => $user->id, 'friend_id' => $data['friend_id']],
            ['nickname' => $data['nickname']]
        );

        return response()->json(['message' => 'Nickname saved.']);
    }

    public function updatePreference(Request $request, int $friendId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $this->areFriends($user->id, $friendId)) {
            return response()->json(['message' => 'Friend not found.'], 404);
        }

        $data = $request->validate([
            'is_favourite' => ['sometimes', 'boolean'],
            'muted' => ['sometimes', 'boolean'],
        ]);

        $preference = FriendPreference::query()->firstOrCreate([
            'user_id' => $user->id,
            'friend_id' => $friendId,
        ]);

        if (array_key_exists('is_favourite', $data)) {
            $preference->is_favourite = (bool) $data['is_favourite'];
        }
        if (array_key_exists('muted', $data)) {
            $preference->muted_at = $data['muted'] ? now() : null;
        }
        $preference->save();

        return response()->json(['message' => 'Friend preference saved.', 'preference' => $preference]);
    }

    private function areFriends(int $a, int $b): bool
    {
        return Friend::query()
            ->where('user_id', $a)
            ->where('friend_id', $b)
            ->where('status', 'accepted')
            ->exists();
    }
}
