<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityEvent;
use App\Models\Challenge;
use App\Models\Item;
use App\Models\Package;
use App\Models\Region;
use App\Models\RunActivity;
use App\Models\ShopItem;
use App\Models\Task;
use App\Models\User;
use App\Models\UserItem;
use App\Models\UserTask;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $totals = [
            'players' => User::query()->count(),
            'runs' => RunActivity::query()->count(),
            'distance_km' => round((float) RunActivity::query()->sum('distance_km'), 2),
            'regions' => Region::query()->count(),
            'quests' => Task::query()->count(),
            'completed_quests' => UserTask::query()->where('status', 'complete')->count(),
            'active_24h' => ActivityEvent::query()->where('created_at', '>=', now()->subDay())->distinct('user_id')->count('user_id'),
            'total_tears_earned' => (int) DB::table('wallet_transactions')->where('type', 'earned')->sum('amount'),
            'total_tears_spent' => (int) DB::table('wallet_transactions')->where('type', 'spent')->sum('amount'),
            'items_in_wild' => UserItem::query()->count(),
            'shop_items' => ShopItem::query()->where('is_active', true)->count(),
            'packages' => Package::query()->count(),
            'active_challenges' => Challenge::query()->where('status', 'active')->count(),
        ];

        $players = User::query()
            ->with('profile')
            ->withCount(['runActivities', 'friends'])
            ->latest()
            ->limit(18)
            ->get()
            ->map(fn (User $player) => [
                'id' => $player->id,
                'name' => $player->name,
                'email' => $player->email,
                'display_name' => $player->profile?->display_name,
                'level' => $player->profile?->level ?? 1,
                'xp' => $player->profile?->xp ?? 0,
                'total_km' => (float) ($player->profile?->total_km ?? 0),
                'total_runs' => $player->run_activities_count,
                'friends' => $player->friends_count,
                'joined_at' => $player->created_at,
            ]);

        $regions = Region::query()
            ->withCount(['tasks', 'runActivities'])
            ->withSum('runActivities', 'distance_km')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Region $region) => [
                'id' => $region->id,
                'name' => $region->name,
                'biome' => $region->biome,
                'difficulty' => $region->difficulty,
                'tasks' => $region->tasks_count,
                'runs' => $region->run_activities_count,
                'distance_km' => round((float) ($region->run_activities_sum_distance_km ?? 0), 2),
                'unlocks' => DB::table('user_region_progress')->where('region_id', $region->id)->where('status', 'unlocked')->count(),
                'avg_progress' => round((float) DB::table('user_region_progress')->where('region_id', $region->id)->avg('progress'), 1),
            ]);

        $quests = Task::query()
            ->with('region')
            ->withCount(['states as completions' => fn ($query) => $query->where('status', 'complete')])
            ->orderBy('region_id')
            ->orderBy('unlock_order')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'region' => $task->region?->name,
                'target_value' => (float) $task->target_value,
                'reward_xp' => $task->reward_xp,
                'completions' => $task->completions,
            ]);

        $activity = ActivityEvent::query()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        return response()->json(compact('totals', 'players', 'regions', 'quests', 'activity'));
    }

    private function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        return (bool) ($user->is_admin ?? false) || collect(config('trailbound.admin_emails', []))->contains(strtolower($user->email));
    }
}
