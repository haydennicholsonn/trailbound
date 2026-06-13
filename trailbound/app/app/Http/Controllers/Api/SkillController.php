<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SkillNode;
use App\Models\UserSkillNode;
use App\Support\Jwt;
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

        return response()->json([
            'branches' => $branches,
            'level' => $profile->level ?? 1,
            'xp' => $profile->xp ?? 0,
            'tears' => $profile->tears ?? 0,
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

        if ($node->cost_tears > 0) {
            $success = WalletController::debit($user->id, $node->cost_tears, 'skill_tree', 'Unlocked skill: ' . $node->name, $node);
            if (! $success) {
                return response()->json([
                    'message' => 'Not enough Tears. You need ' . $node->cost_tears . ' Tears.',
                    'balance' => $profile->tears ?? 0,
                    'required' => $node->cost_tears,
                ], 402);
            }
        }

        UserSkillNode::query()->create([
            'user_id' => $user->id,
            'skill_node_id' => $node->id,
            'unlocked_at' => now(),
        ]);

        return response()->json([
            'message' => 'Skill unlocked: ' . $node->name,
            'balance' => $user->profile->fresh()->tears ?? 0,
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
