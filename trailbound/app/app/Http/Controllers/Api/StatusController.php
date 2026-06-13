<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\User;
use App\Models\UserStatus;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class StatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $status = UserStatus::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'status' => $status,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'status_text' => ['required', 'string', 'max:300'],
            'mood' => ['nullable', 'string', 'max:30'],
        ]);

        $status = UserStatus::query()->create([
            'user_id' => $user->id,
            'status_text' => $data['status_text'],
            'mood' => $data['mood'] ?? null,
        ]);

        ActivityEvent::query()->create([
            'user_id' => $user->id,
            'type' => 'status_update',
            'payload' => [
                'status_text' => $data['status_text'],
                'mood' => $data['mood'] ?? null,
            ],
        ]);

        Realtime::publish('notifications.updated', ['reason' => 'status', 'user_id' => $user->id]);

        return response()->json([
            'status' => $status,
        ]);
    }

    public function userStatus(int $userId, Request $request): JsonResponse
    {
        $currentUser = Jwt::userFromRequest($request);
        if (!$currentUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $target = User::query()->find($userId);
        if (!$target) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $isFriend = \App\Models\Friend::query()
            ->where(function ($q) use ($currentUser, $userId) {
                $q->where('user_id', $currentUser->id)->where('friend_id', $userId)->where('status', 'accepted');
            })
            ->orWhere(function ($q) use ($currentUser, $userId) {
                $q->where('user_id', $userId)->where('friend_id', $currentUser->id)->where('status', 'accepted');
            })
            ->exists();

        if ($target->profile?->privacy_level === 'private' && !$isFriend && $userId !== $currentUser->id) {
            return response()->json(['message' => 'This profile is private.'], 403);
        }

        $statuses = UserStatus::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'user' => [
                'id' => $target->id,
                'name' => $target->name,
                'display_name' => $target->profile?->display_name,
                'runner_type' => $target->profile?->runner_type,
                'level' => $target->profile?->level,
                'xp' => $target->profile?->xp,
                'total_km' => $target->profile?->total_km,
                'avatar_path' => $target->profile?->avatar_path,
                'bio' => $target->profile?->bio,
                'home_area' => $target->profile?->home_area,
            ],
            'statuses' => $statuses,
        ]);
    }
}
