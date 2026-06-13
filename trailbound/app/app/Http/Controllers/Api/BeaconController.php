<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Friend;
use App\Models\MapBeacon;
use App\Models\Region;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeaconController extends Controller
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
            ->push($user->id);

        $beacons = MapBeacon::query()
            ->with(['user.profile', 'region'])
            ->whereIn('user_id', $friendIds)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (MapBeacon $beacon) => $this->payload($beacon));

        return response()->json(['beacons' => $beacons]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'title' => ['required', 'string', 'max:80'],
            'kind' => ['nullable', 'in:rally,route,challenge,flare'],
            'note' => ['nullable', 'string', 'max:280'],
        ]);

        $regionId = $data['region_id'] ?? Region::query()->orderBy('sort_order')->first()?->id;
        $beacon = MapBeacon::query()->create([
            'user_id' => $user->id,
            'region_id' => $regionId,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'title' => $data['title'],
            'kind' => $data['kind'] ?? 'rally',
            'note' => $data['note'] ?? null,
            'expires_at' => now()->addHours(6),
        ]);

        ActivityEvent::query()->create([
            'user_id' => $user->id,
            'type' => 'beacon_dropped',
            'payload' => [
                'title' => $beacon->title,
                'kind' => $beacon->kind,
                'region_id' => $beacon->region_id,
            ],
        ]);

        Realtime::publish('map.updated', ['reason' => 'beacon', 'user_id' => $user->id]);

        return response()->json(['beacon' => $this->payload($beacon->fresh(['user.profile', 'region']))]);
    }

    private function payload(MapBeacon $beacon): array
    {
        return [
            'id' => $beacon->id,
            'lat' => (float) $beacon->lat,
            'lng' => (float) $beacon->lng,
            'title' => $beacon->title,
            'kind' => $beacon->kind,
            'note' => $beacon->note,
            'expires_at' => $beacon->expires_at,
            'region' => $beacon->region ? [
                'id' => $beacon->region->id,
                'name' => $beacon->region->name,
                'biome' => $beacon->region->biome,
            ] : null,
            'user' => [
                'id' => $beacon->user->id,
                'name' => $beacon->user->name,
                'display_name' => $beacon->user->profile?->display_name,
                'avatar_path' => $beacon->user->profile?->avatar_path,
            ],
        ];
    }
}
