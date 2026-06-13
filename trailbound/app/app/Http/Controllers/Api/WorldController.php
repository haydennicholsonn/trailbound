<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Region;
use App\Models\RunActivity;
use App\Models\Task;
use App\Models\Friend;
use App\Models\UserItem;
use App\Models\UserRegionProgress;
use App\Models\UserTask;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorldController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $regions = Region::query()->with('tasks')->orderBy('sort_order')->get();
        $progress = $user->regionProgress()->get()->keyBy('region_id');
        $taskStates = $user->taskStates()->get()->keyBy('task_id');

        return response()->json([
            'regions' => $regions->map(fn (Region $region) => [
                'id' => $region->id,
                'key' => $region->key,
                'name' => $region->name,
                'biome' => $region->biome,
                'summary' => $region->summary,
                'difficulty' => $region->difficulty,
                'map_x' => $region->map_x,
                'map_y' => $region->map_y,
                'polygon' => $region->polygon,
                'start_keywords' => $region->start_keywords,
                'status' => $progress[$region->id]->status ?? 'locked',
                'progress' => $progress[$region->id]->progress ?? 0,
                'tasks' => $region->tasks->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'unlock_rule' => $task->unlock_rule,
                    'reward_xp' => $task->reward_xp,
                    'reward_tears' => $task->reward_tears ?? 0,
                    'reward_item_id' => $task->reward_item_id,
                    'status' => $taskStates[$task->id]->status ?? ($task->unlock_order === 1 && ($progress[$region->id]->status ?? null) === 'unlocked' ? 'available' : 'locked'),
                ]),
            ]),
            'recent_runs' => $user->runActivities()->latest('run_at')->limit(8)->get(),
        ]);
    }

    public function storeRun(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'distance_km' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'duration_minutes' => ['required', 'numeric', 'min:3', 'max:900'],
            'region_id' => ['required', 'exists:regions,id'],
            'run_at' => ['nullable', 'date'],
        ]);

        $xp = (int) round($data['distance_km'] * 85);
        $run = RunActivity::query()->create([
            'user_id' => $user->id,
            'region_id' => $data['region_id'],
            'distance_km' => $data['distance_km'],
            'duration_minutes' => $data['duration_minutes'],
            'xp_awarded' => $xp,
            'source' => 'manual',
            'run_at' => $data['run_at'] ?? now(),
        ]);

        $profile = $user->profile;
        $profile->increment('total_runs');
        $profile->increment('xp', $xp);
        $profile->total_km = round(((float) $profile->total_km) + (float) $data['distance_km'], 2);
        $profile->level = max(1, (int) floor(sqrt($profile->xp / 500)) + 1);
        $profile->save();

        $progress = UserRegionProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'region_id' => $data['region_id']],
            ['status' => 'unlocked', 'progress' => 0, 'unlocked_at' => now()]
        );
        $progress->progress = min(100, $progress->progress + (int) ceil($data['distance_km'] * 8));
        $progress->status = 'unlocked';
        $progress->save();

        $this->unlockTasks($user->id, $data['region_id'], $data['distance_km']);

        ActivityEvent::query()->create([
            'user_id' => $user->id,
            'type' => 'run_logged',
            'payload' => [
                'run_id' => $run->id,
                'distance_km' => $data['distance_km'],
                'duration_minutes' => $data['duration_minutes'],
                'xp' => $xp,
                'region_id' => $data['region_id'],
            ],
        ]);

        Realtime::publish('world.updated', ['reason' => 'run', 'user_id' => $user->id, 'region_id' => $data['region_id']]);
        Realtime::publish('notifications.updated', ['reason' => 'run', 'user_id' => $user->id]);

        return response()->json([
            'run' => $run,
            'message' => "Run logged. {$xp} XP awarded.",
        ]);
    }

    public function showRun(Request $request, int $runId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $run = RunActivity::query()->with(['user.profile', 'region.tasks'])->find($runId);
        if (! $run || ! $this->canViewRun($user->id, $run->user_id)) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        return response()->json(['run' => $this->runDashboardPayload($run)]);
    }

    public function uploadImages(Request $request, int $runId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'images' => ['required', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'max:10240'],
        ]);

        $run = RunActivity::query()->where('id', $runId)->where('user_id', $user->id)->first();
        if (!$run) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        $paths = $run->image_paths ?? [];

        foreach ($request->file('images') as $image) {
            $path = $image->store('runs/' . $runId, 'public');
            $paths[] = $path;
        }

        $run->update(['image_paths' => $paths]);

        return response()->json([
            'images' => array_map(fn (string $p) => Storage::url($p), $paths),
        ]);
    }

    private function unlockTasks(int $userId, int $regionId, float $distance): void
    {
        Task::query()->where('region_id', $regionId)->get()->each(function (Task $task) use ($userId, $distance) {
            $state = UserTask::query()->firstOrCreate(
                ['user_id' => $userId, 'task_id' => $task->id],
                ['status' => 'available', 'progress' => 0]
            );

            if ($distance >= (float) ($task->target_value ?? 2)) {
                if ($state->status !== 'complete') {
                    $state->status = 'complete';
                    $state->progress = 100;
                    $state->completed_at = now();
                    $state->save();

                    if ($task->reward_tears > 0) {
                        WalletController::credit($userId, $task->reward_tears, 'quest', 'Quest complete: ' . $task->title, $task);
                    }
                    if ($task->reward_item_id) {
                        UserItem::query()->firstOrCreate(
                            ['user_id' => $userId, 'item_id' => $task->reward_item_id],
                            ['quantity' => 1, 'acquired_at' => now(), 'acquired_from' => 'quest']
                        );
                    }
                }
            } else {
                $state->progress = min(99, $state->progress + (int) ceil($distance * 20));
                $state->save();
            }
        });
    }

    private function canViewRun(int $viewerId, int $ownerId): bool
    {
        if ($viewerId === $ownerId) {
            return true;
        }

        return Friend::query()
            ->where('user_id', $viewerId)
            ->where('friend_id', $ownerId)
            ->where('status', 'accepted')
            ->exists();
    }

    private function runDashboardPayload(RunActivity $run): array
    {
        $distance = max(0.01, (float) $run->distance_km);
        $duration = max(0.1, (float) $run->duration_minutes);
        $pace = $duration / $distance;
        $speed = $distance / ($duration / 60);
        $splitCount = max(1, (int) ceil($distance));

        $splits = collect(range(1, $splitCount))->map(function (int $km) use ($pace, $splitCount, $distance) {
            $drift = sin($km * 1.71) * 0.18 + (($km % 3) - 1) * 0.05;
            $splitDistance = $km === $splitCount ? round($distance - floor($distance) ?: 1, 2) : 1;
            return [
                'km' => $km,
                'distance_km' => $splitDistance,
                'pace_min_km' => round(max(2.5, $pace + $drift), 2),
            ];
        })->values();

        $tasks = $run->region?->tasks
            ? $run->region->tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'unlock_rule' => $task->unlock_rule,
                'target_value' => (float) $task->target_value,
                'reward_xp' => $task->reward_xp,
                'unlocked_by_run' => $distance >= (float) $task->target_value,
            ])->values()
            : collect();

        return [
            'id' => $run->id,
            'distance_km' => (float) $run->distance_km,
            'duration_minutes' => (float) $run->duration_minutes,
            'pace_min_km' => round($pace, 2),
            'pace_label' => $this->paceLabel($pace),
            'speed_kmh' => round($speed, 1),
            'xp_awarded' => $run->xp_awarded,
            'source' => $run->source,
            'run_at' => $run->run_at,
            'image_paths' => $run->image_paths,
            'region' => $run->region ? [
                'id' => $run->region->id,
                'name' => $run->region->name,
                'biome' => $run->region->biome,
                'difficulty' => $run->region->difficulty,
            ] : null,
            'user' => [
                'id' => $run->user->id,
                'name' => $run->user->name,
                'display_name' => $run->user->profile?->display_name,
                'avatar_path' => $run->user->profile?->avatar_path,
            ],
            'splits' => $splits,
            'chart' => [
                'pace' => $splits->pluck('pace_min_km'),
                'labels' => $splits->map(fn ($split) => 'K' . $split['km']),
            ],
            'quest_unlocks' => $tasks,
        ];
    }

    private function paceLabel(float $pace): string
    {
        $minutes = (int) floor($pace);
        $seconds = (int) round(($pace - $minutes) * 60);
        return sprintf('%d:%02d /km', $minutes, $seconds);
    }
}
