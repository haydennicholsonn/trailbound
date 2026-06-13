<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\RunActivity;
use App\Models\StravaConnection;
use App\Models\StravaWebhookEvent;
use App\Models\UserRegionProgress;
use App\Models\UserTask;
use App\Models\Task;
use App\Models\Region;
use App\Support\Jwt;
use App\Support\StravaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StravaController extends Controller
{
    public function connect(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            return response()->json(['url' => StravaService::authorizationUrl()]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        try {
            $tokenData = StravaService::exchangeCode($data['code']);
            $connection = StravaService::storeConnection($user->id, $tokenData);

            // Create activity event
            ActivityEvent::query()->create([
                'user_id' => $user->id,
                'type' => 'strava_connected',
                'payload' => ['athlete_id' => $connection->strava_athlete_id],
            ]);

            // Immediately attempt first sync
            $syncResult = $this->doSync($user->id, $connection);

            return response()->json([
                'connected' => true,
                'athlete_id' => $connection->strava_athlete_id,
                'sync' => $syncResult,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function status(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $connection = $user->stravaConnection;

        return response()->json([
            'connected' => $connection && $connection->is_active,
            'athlete_id' => $connection->strava_athlete_id ?? null,
            'last_sync_at' => $connection?->last_sync_at,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $connection = $user->stravaConnection;
        if (!$connection || !$connection->is_active) {
            return response()->json(['message' => 'Strava is not connected.'], 400);
        }

        try {
            $result = $this->doSync($user->id, $connection);
            return response()->json($result);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function disconnect(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $connection = $user->stravaConnection;
        if ($connection) {
            $connection->update([
                'is_active' => false,
                'access_token' => null,
                'refresh_token' => null,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function webhook(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        if ($request->isMethod('GET')) {
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if (!$mode || !$token || !$challenge) {
                return response()->json(['message' => 'Invalid webhook verification request.'], 400);
            }

            try {
                return response(StravaService::verifyWebhookChallenge($mode, $token, $challenge), 200)
                    ->header('Content-Type', 'application/json');
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
        }

        $data = $request->validate([
            'aspect_type' => ['required', 'string'],
            'event_time' => ['required', 'integer'],
            'object_id' => ['required', 'integer'],
            'object_type' => ['required', 'string'],
            'owner_id' => ['required', 'integer'],
        ]);

        $connection = StravaConnection::query()
            ->where('strava_athlete_id', $data['owner_id'])
            ->where('is_active', true)
            ->first();

        if ($connection) {
            StravaWebhookEvent::query()->create([
                'connection_id' => $connection->id,
                'object_type' => $data['object_type'],
                'object_id' => $data['object_id'],
                'aspect_type' => $data['aspect_type'],
                'owner_id' => $data['owner_id'],
                'event_time' => $data['event_time'],
                'updates' => $data['updates'] ?? null,
            ]);

            if ($data['object_type'] === 'activity' && ($data['aspect_type'] === 'create' || $data['aspect_type'] === 'update')) {
                dispatch(fn () => $this->doSync($connection->user_id, $connection))->afterResponse();
            }
        }

        return response()->json(['ok' => true]);
    }

    private function doSync(int $userId, StravaConnection $connection): array
    {
        $imported = [];

        try {
            $activities = StravaService::fetchActivities($connection);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Failed to fetch activities: ' . $e->getMessage());
        }

        foreach ($activities as $rawActivity) {
            if (!StravaService::isRun($rawActivity)) {
                continue;
            }

            $normalized = StravaService::normalizeActivity($rawActivity);

            $existing = RunActivity::query()
                ->where('user_id', $userId)
                ->where('external_id', $normalized['external_id'])
                ->exists();

            if ($existing) {
                continue;
            }

            $regionId = null;
            $profile = $connection->user->profile;
            if ($profile) {
                $region = Region::startingFor($profile->home_area);
                $regionId = $region->id;
                UserRegionProgress::query()->firstOrCreate(
                    ['user_id' => $userId, 'region_id' => $region->id],
                    ['status' => 'unlocked', 'progress' => 0, 'unlocked_at' => now()]
                );
            }

            $xp = (int) round($normalized['distance_km'] * 85);
            $run = RunActivity::query()->create([
                'user_id' => $userId,
                'region_id' => $regionId,
                'distance_km' => $normalized['distance_km'],
                'duration_minutes' => $normalized['duration_minutes'],
                'xp_awarded' => $xp,
                'source' => 'strava',
                'external_id' => $normalized['external_id'],
                'run_at' => $normalized['run_at'],
                'polyline' => $normalized['polyline'],
                'start_lat' => $normalized['start_lat'],
                'start_lng' => $normalized['start_lng'],
            ]);

            // Process game mechanics
            if ($profile) {
                $profile->increment('total_runs');
                $profile->increment('xp', $xp);
                $profile->total_km = round((float) $profile->total_km + $normalized['distance_km'], 2);
                $profile->level = max(1, (int) floor(sqrt(($profile->xp ?? 0) / 500)) + 1);
                $profile->save();
            }

            if ($regionId) {
                $progress = UserRegionProgress::query()->firstOrCreate(
                    ['user_id' => $userId, 'region_id' => $regionId],
                    ['status' => 'unlocked', 'progress' => 0, 'unlocked_at' => now()]
                );
                $progress->progress = min(100, $progress->progress + (int) ceil($normalized['distance_km'] * 8));
                $progress->status = 'unlocked';
                $progress->save();

                Task::query()->where('region_id', $regionId)->get()->each(function (Task $task) use ($userId, $normalized) {
                    $state = UserTask::query()->firstOrCreate(
                        ['user_id' => $userId, 'task_id' => $task->id],
                        ['status' => 'available', 'progress' => 0]
                    );
                    if ($normalized['distance_km'] >= (float) ($task->target_value ?? 2)) {
                        $state->status = 'complete';
                        $state->progress = 100;
                        $state->completed_at = now();
                    } else {
                        $state->progress = min(99, $state->progress + (int) ceil($normalized['distance_km'] * 20));
                    }
                    $state->save();
                });
            }

            ActivityEvent::query()->create([
                'user_id' => $userId,
                'type' => 'run_imported',
                'payload' => [
                    'run_id' => $run->id,
                    'distance_km' => $normalized['distance_km'],
                    'duration_minutes' => $normalized['duration_minutes'],
                    'xp' => $xp,
                    'source' => 'strava',
                ],
            ]);

            $imported[] = [
                'id' => $run->id,
                'distance_km' => $normalized['distance_km'],
                'xp' => $xp,
            ];
        }

        $connection->update(['last_sync_at' => now()]);

        return [
            'imported' => count($imported),
            'activities' => $imported,
        ];
    }
}
