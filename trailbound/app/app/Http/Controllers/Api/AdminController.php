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
use App\Models\WalletTransaction;
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
            'referrals' => DB::table('user_profiles')->whereNotNull('referred_by_user_id')->count(),
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
                'friend_code' => $player->profile?->friend_code,
                'tears' => $player->profile?->tears ?? 0,
                'skill_points' => $player->profile?->skill_points ?? 0,
                'package_id' => $player->profile?->package_id,
                'package' => $player->profile?->package_id ? Package::query()->find($player->profile->package_id)?->name : null,
                'is_admin' => (bool) $player->is_admin,
                'lifecycle_stage' => $player->profile?->lifecycle_stage ?? 'new',
                'admin_notes' => $player->profile?->admin_notes,
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

        $referrals = User::query()
            ->with('profile')
            ->withCount(['referrals'])
            ->having('referrals_count', '>', 0)
            ->orderByDesc('referrals_count')
            ->limit(10)
            ->get()
            ->map(fn (User $player) => [
                'id' => $player->id,
                'name' => $player->profile?->display_name ?: $player->name,
                'email' => $player->email,
                'friend_code' => $player->profile?->friend_code,
                'referrals' => $player->referrals_count,
            ]);

        $recentReferrals = DB::table('user_profiles as child_profiles')
            ->join('users as children', 'children.id', '=', 'child_profiles.user_id')
            ->join('users as parents', 'parents.id', '=', 'child_profiles.referred_by_user_id')
            ->leftJoin('user_profiles as parent_profiles', 'parent_profiles.user_id', '=', 'parents.id')
            ->whereNotNull('child_profiles.referred_by_user_id')
            ->latest('children.created_at')
            ->limit(12)
            ->get([
                'children.name as child_name',
                'children.email as child_email',
                'parents.name as parent_name',
                'parent_profiles.display_name as parent_display_name',
                'children.created_at',
            ]);

        $packageMix = DB::table('packages')
            ->leftJoin('user_profiles', 'user_profiles.package_id', '=', 'packages.id')
            ->select('packages.id', 'packages.name', 'packages.key', 'packages.price_cents', DB::raw('count(user_profiles.id) as users'))
            ->groupBy('packages.id', 'packages.name', 'packages.key', 'packages.price_cents')
            ->orderBy('packages.sort_order')
            ->get();

        $challengeMix = Challenge::query()
            ->select('status', 'type', DB::raw('count(*) as total'))
            ->groupBy('status', 'type')
            ->orderBy('type')
            ->get();

        return response()->json(compact('totals', 'players', 'regions', 'quests', 'activity', 'referrals', 'recentReferrals', 'packageMix', 'challengeMix'));
    }

    public function players(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $search = trim((string) $request->query('search', ''));
        $stage = $request->query('stage');
        $packageId = $request->query('package_id');

        $players = User::query()
            ->with('profile')
            ->withCount(['runActivities', 'friends'])
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('profile', fn ($profile) => $profile->where('display_name', 'ilike', "%{$search}%")->orWhere('friend_code', 'ilike', "%{$search}%"));
            }))
            ->when($stage && $stage !== 'all', fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('lifecycle_stage', $stage)))
            ->when($packageId && $packageId !== 'all', fn ($query) => $query->whereHas('profile', fn ($profile) => $profile->where('package_id', $packageId)))
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (User $player) => $this->playerPayload($player));

        return response()->json([
            'players' => $players,
            'packages' => Package::query()->orderBy('sort_order')->get(['id', 'key', 'name']),
            'stages' => ['new', 'active', 'at_risk', 'vip', 'founder', 'support'],
        ]);
    }

    public function updatePlayer(Request $request, int $playerId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $player = User::query()->with('profile')->find($playerId);
        if (! $player || ! $player->profile) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $data = $request->validate([
            'is_admin' => ['nullable', 'boolean'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'lifecycle_stage' => ['nullable', 'string', 'max:40'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'skill_points' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        if (array_key_exists('is_admin', $data)) {
            $player->update(['is_admin' => (bool) $data['is_admin']]);
        }

        $player->profile->update(collect($data)->only(['package_id', 'lifecycle_stage', 'admin_notes', 'skill_points'])->all());

        return response()->json([
            'message' => 'Player updated.',
            'player' => $this->playerPayload($player->fresh('profile')),
        ]);
    }

    public function adjustTears(Request $request, int $playerId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $player = User::query()->with('profile')->find($playerId);
        if (! $player || ! $player->profile) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:-10000', 'max:10000', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $newBalance = max(0, (int) $player->profile->tears + (int) $data['amount']);
        $type = ((int) $data['amount']) > 0 ? 'earned' : 'spent';
        $player->profile->update(['tears' => $newBalance]);
        WalletTransaction::query()->create([
            'user_id' => $player->id,
            'type' => $type,
            'amount' => abs((int) $data['amount']),
            'balance_after' => $newBalance,
            'source' => 'admin',
            'note' => $data['note'] ?? 'Admin CRM adjustment',
        ]);

        return response()->json([
            'message' => 'Tears adjusted.',
            'player' => $this->playerPayload($player->fresh('profile')),
        ]);
    }

    private function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        return (bool) ($user->is_admin ?? false) || collect(config('trailbound.admin_emails', []))->contains(strtolower($user->email));
    }

    private function playerPayload(User $player): array
    {
        $package = $player->profile?->package_id ? Package::query()->find($player->profile->package_id) : null;

        return [
            'id' => $player->id,
            'name' => $player->name,
            'email' => $player->email,
            'display_name' => $player->profile?->display_name,
            'level' => $player->profile?->level ?? 1,
            'xp' => $player->profile?->xp ?? 0,
            'tears' => $player->profile?->tears ?? 0,
            'skill_points' => $player->profile?->skill_points ?? 0,
            'total_km' => (float) ($player->profile?->total_km ?? 0),
            'total_runs' => $player->run_activities_count ?? $player->runActivities()->count(),
            'friends' => $player->friends_count ?? $player->friends()->count(),
            'friend_code' => $player->profile?->friend_code,
            'package_id' => $player->profile?->package_id,
            'package' => $package?->name,
            'is_admin' => (bool) $player->is_admin,
            'lifecycle_stage' => $player->profile?->lifecycle_stage ?? 'new',
            'admin_notes' => $player->profile?->admin_notes,
            'joined_at' => $player->created_at,
        ];
    }
}
