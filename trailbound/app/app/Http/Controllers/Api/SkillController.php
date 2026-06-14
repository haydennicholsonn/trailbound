<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SkillNode;
use App\Models\UserSkillNode;
use App\Support\Jwt;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function tree(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = $user->profile;
        $unlockedNodeIds = UserSkillNode::query()->where('user_id', $user->id)->pluck('skill_node_id');

        $nodes = SkillNode::query()
            ->where('is_active', true)
            ->orderBy('branch')
            ->orderBy('tier')
            ->orderBy('position')
            ->get()
            ->map(fn (SkillNode $node) => [
                'id' => $node->id,
                'key' => $node->key,
                'name' => $node->name,
                'icon' => $node->icon,
                'description' => $node->description,
                'branch' => $node->branch,
                'tier' => $node->tier,
                'position' => $node->position,
                'requirement_type' => $node->requirement_type,
                'requirement_value' => $node->requirement_value,
                'cost_tears' => $node->cost_tears,
                'effect' => $node->effect,
                'effect_stat' => $node->effect_stat,
                'effect_value' => $node->effect_value,
                'prerequisite_keys' => $node->prerequisite_keys,
                'unlocked' => $unlockedNodeIds->contains($node->id),
                'available' => $this->isAvailable($node, $profile, $unlockedNodeIds),
            ]);

        $branches = $nodes->groupBy('branch');
        $spent = $unlockedNodeIds->count();
        $earned = max(0, (int) ($profile->level ?? 1) - 1);
        $available = max((int) ($profile->skill_points ?? 0), max(0, $earned - $spent));
        if ($profile && (int) ($profile->skill_points ?? 0) !== $available) {
            $profile->update(['skill_points' => $available]);
        }
        $nextFreeRespecAt = $profile?->last_respec_at?->copy()->addWeek();
        $freeRespecAvailable = ! $profile?->last_respec_at || $profile->last_respec_at->lte(now()->subWeek());

        return response()->json([
            'branches' => $branches,
            'level' => $profile->level ?? 1,
            'xp' => $profile->xp ?? 0,
            'tears' => $profile->tears ?? 0,
            'skill_points' => $available,
            'spent_points' => $spent,
            'earned_points' => $earned,
            'free_respec_available' => $freeRespecAvailable,
            'next_free_respec_at' => $freeRespecAvailable ? null : $nextFreeRespecAt?->toIso8601String(),
            'respec_cost_tears' => $freeRespecAvailable ? 0 : 10,
        ]);
    }

    public function unlock(Request $request, int $nodeId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $node = SkillNode::query()->find($nodeId);
        if (! $node || ! $node->is_active) {
            return response()->json(['message' => 'Skill node not found.'], 404);
        }

        $profile = $user->profile;
        $unlockedNodeIds = UserSkillNode::query()->where('user_id', $user->id)->pluck('skill_node_id');

        if ($unlockedNodeIds->contains($node->id)) {
            return response()->json(['message' => 'Already unlocked.'], 409);
        }

        if (! $this->isAvailable($node, $profile, $unlockedNodeIds)) {
            return response()->json(['message' => 'Requirements not met.'], 403);
        }

        $spent = $unlockedNodeIds->count();
        $earned = max(0, (int) ($profile->level ?? 1) - 1);
        $available = max((int) ($profile->skill_points ?? 0), max(0, $earned - $spent));
        if ($available < 1) {
            return response()->json(['message' => 'No skill points available. Level up to earn another point.'], 402);
        }

        DB::transaction(function () use ($user, $node, $profile, $available) {
            UserSkillNode::query()->create([
                'user_id' => $user->id,
                'skill_node_id' => $node->id,
                'unlocked_at' => now(),
            ]);
            $profile->update(['skill_points' => max(0, $available - 1)]);
        });

        return response()->json([
            'message' => 'Skill unlocked: ' . $node->name,
            'skill_points' => $user->profile->fresh()->skill_points ?? 0,
            'balance' => $user->profile->fresh()->tears ?? 0,
        ]);
    }

    public function respec(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = $user->profile;
        $spent = UserSkillNode::query()->where('user_id', $user->id)->count();
        if ($spent === 0) {
            return response()->json(['message' => 'You have no unlocked skills to respec.'], 422);
        }

        $free = ! $profile->last_respec_at || $profile->last_respec_at->lte(now()->subWeek());
        if (! $free && ! WalletController::debit($user->id, 10, 'skill_respec', 'Skill tree respec')) {
            return response()->json(['message' => 'Respec costs 10 Tears until your weekly free respec resets.'], 402);
        }

        DB::transaction(function () use ($user, $profile, $spent) {
            UserSkillNode::query()->where('user_id', $user->id)->delete();
            $profile->update([
                'skill_points' => max(0, (int) $profile->skill_points) + $spent,
                'last_respec_at' => now(),
                'respec_count' => ((int) $profile->respec_count) + 1,
            ]);
        });

        return response()->json([
            'message' => $free ? 'Skill tree reset. Weekly free respec used.' : 'Skill tree reset for 10 Tears.',
            'skill_points' => $profile->fresh()->skill_points,
            'tears' => $profile->fresh()->tears,
        ]);
    }

    private function isAvailable(SkillNode $node, $profile, $unlockedNodeIds): bool
    {
        $prereqs = $node->prerequisite_keys ?? [];
        if (! empty($prereqs)) {
            $prereqIds = SkillNode::query()->whereIn('key', $prereqs)->pluck('id');
            foreach ($prereqIds as $id) {
                if (! $unlockedNodeIds->contains($id)) {
                    return false;
                }
            }
        }

        switch ($node->requirement_type) {
            case 'level':
                return ($profile->level ?? 1) >= $node->requirement_value;
            case 'xp':
                return ($profile->xp ?? 0) >= $node->requirement_value;
            case 'distance':
                return ($profile->total_km ?? 0) >= $node->requirement_value;
            default:
                return true;
        }
    }
}
