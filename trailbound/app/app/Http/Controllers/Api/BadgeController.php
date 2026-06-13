<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = $user->profile;
        $userBadgeIds = UserBadge::query()->where('user_id', $user->id)->pluck('badge_id');

        $badges = Badge::query()
            ->where('is_active', true)
            ->orderBy('level_required')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Badge $badge) => [
                'id' => $badge->id,
                'key' => $badge->key,
                'name' => $badge->name,
                'icon' => $badge->icon,
                'description' => $badge->description,
                'level_required' => $badge->level_required,
                'category' => $badge->category,
                'unlocked' => $userBadgeIds->contains($badge->id),
                'earned' => ($profile->level ?? 1) >= $badge->level_required,
            ]);

        $currentLevel = $profile->level ?? 1;
        $currentBadge = $badges->where('level_required', '<=', $currentLevel)->sortByDesc('level_required')->first();
        $nextBadge = $badges->where('level_required', '>', $currentLevel)->sortBy('level_required')->first();

        return response()->json([
            'badges' => $badges,
            'current_badge' => $currentBadge,
            'next_badge' => $nextBadge,
            'level' => $currentLevel,
        ]);
    }

    public static function awardBadge(int $userId, int $badgeId): bool
    {
        $exists = UserBadge::query()->where('user_id', $userId)->where('badge_id', $badgeId)->exists();
        if ($exists) return false;

        UserBadge::query()->create([
            'user_id' => $userId,
            'badge_id' => $badgeId,
            'awarded_at' => now(),
        ]);

        return true;
    }
}
