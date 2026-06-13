<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Models\ActivityEvent;
use App\Models\Region;
use App\Models\UserLocation;
use App\Models\UserRegionProgress;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'share_mode' => ['nullable', 'in:off,friends'],
        ]);

        $region = $this->regionFor((float) $data['lat'], (float) $data['lng']);
        $discovery = null;

        if ($region) {
            $discovery = UserRegionProgress::query()->firstOrCreate(
                ['user_id' => $user->id, 'region_id' => $region->id],
                ['status' => 'unlocked', 'progress' => 1, 'unlocked_at' => now()]
            );

            if ($discovery->wasRecentlyCreated) {
                ActivityEvent::query()->create([
                    'user_id' => $user->id,
                    'type' => 'region_discovered',
                    'payload' => [
                        'region_id' => $region->id,
                        'region_name' => $region->name,
                        'source' => 'location_heartbeat',
                    ],
                ]);
            } elseif ($discovery->status === 'locked') {
                $discovery->status = 'unlocked';
                $discovery->progress = max(1, (int) $discovery->progress);
                $discovery->unlocked_at ??= now();
                $discovery->save();
            }
        }

        $location = UserLocation::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'accuracy_m' => $data['accuracy_m'] ?? null,
                'region_id' => $region?->id,
                'share_mode' => $data['share_mode'] ?? 'friends',
                'seen_at' => now(),
            ]
        );

        Realtime::publish('map.updated', ['reason' => 'location', 'user_id' => $user->id]);

        return response()->json([
            'location' => $this->payload($location->fresh(['region', 'user.profile'])),
            'discovery' => $discovery ? [
                'region_id' => $discovery->region_id,
                'status' => $discovery->status,
                'progress' => $discovery->progress,
                'unlocked_at' => $discovery->unlocked_at,
            ] : null,
        ]);
    }

    public function friends(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $friendIds = Friend::query()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('friend_id');

        $locations = UserLocation::query()
            ->with(['user.profile', 'region'])
            ->whereIn('user_id', $friendIds)
            ->where('share_mode', 'friends')
            ->where('seen_at', '>=', now()->subMinutes(20))
            ->latest('seen_at')
            ->get()
            ->map(fn (UserLocation $location) => $this->payload($location));

        $mine = UserLocation::query()->with(['user.profile', 'region'])->where('user_id', $user->id)->first();

        return response()->json([
            'me' => $mine ? $this->payload($mine) : null,
            'friends' => $locations,
        ]);
    }

    private function payload(UserLocation $location): array
    {
        return [
            'user_id' => $location->user_id,
            'lat' => (float) $location->lat,
            'lng' => (float) $location->lng,
            'accuracy_m' => $location->accuracy_m,
            'seen_at' => $location->seen_at,
            'region' => $location->region ? [
                'id' => $location->region->id,
                'name' => $location->region->name,
                'biome' => $location->region->biome,
            ] : null,
            'user' => [
                'id' => $location->user->id,
                'name' => $location->user->name,
                'display_name' => $location->user->profile?->display_name,
                'avatar_path' => $location->user->profile?->avatar_path,
                'runner_type' => $location->user->profile?->runner_type,
            ],
        ];
    }

    private function regionFor(float $lat, float $lng): ?Region
    {
        return Region::query()->orderBy('sort_order')->get()->first(function (Region $region) use ($lat, $lng) {
            $ring = $region->polygon['coordinates'][0] ?? null;
            return is_array($ring) && $this->pointInPolygon($lng, $lat, $ring);
        });
    }

    private function pointInPolygon(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 0.0000001) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }
}
