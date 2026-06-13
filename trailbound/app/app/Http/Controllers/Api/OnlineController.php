<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class OnlineController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Redis::setex('online:' . $user->id, 90, time());

        return response()->json(['ok' => true]);
    }

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

        $onlineIds = [];
        foreach ($friendIds as $fid) {
            if (Redis::exists('online:' . $fid)) {
                $onlineIds[] = (int) $fid;
            }
        }

        return response()->json([
            'online' => $onlineIds,
        ]);
    }
}
