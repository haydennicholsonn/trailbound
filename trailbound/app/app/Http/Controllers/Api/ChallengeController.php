<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\Friend;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function official(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $challenges = Challenge::query()
            ->with(['participants' => fn ($q) => $q->where('user_id', $user->id)])
            ->whereIn('type', ['official_daily', 'official_weekly', 'official_monthly'])
            ->where('status', 'active')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Challenge $challenge) => $this->formatChallenge($challenge, $user->id));

        return response()->json(['challenges' => $challenges]);
    }

    public function friendChallenges(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $friendIds = Friend::query()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('friend_id');

        $allIds = $friendIds->push($user->id);

        $challenges = Challenge::query()
            ->with(['participants.user.profile', 'creator.profile'])
            ->where('type', 'friend')
            ->where(function ($q) use ($allIds) {
                $q->whereIn('created_by', $allIds)
                  ->orWhereHas('participants', fn ($q) => $q->whereIn('user_id', $allIds->toArray()));
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Challenge $challenge) => $this->formatChallenge($challenge, $user->id));

        return response()->json(['challenges' => $challenges]);
    }

    public function create(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'friend_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:200'],
            'goal_type' => ['required', 'string'],
            'goal_value' => ['required', 'integer', 'min:1'],
            'reward_tears' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $isFriend = Friend::query()
            ->where('user_id', $user->id)
            ->where('friend_id', $data['friend_id'])
            ->where('status', 'accepted')
            ->exists();

        if (! $isFriend) {
            return response()->json(['message' => 'You can only challenge accepted friends.'], 403);
        }

        $challenge = Challenge::query()->create([
            'type' => 'friend',
            'title' => $data['title'],
            'goal_type' => $data['goal_type'],
            'goal_value' => $data['goal_value'],
            'goal_label' => $this->goalLabel($data['goal_type'], $data['goal_value']),
            'reward_tears' => $data['reward_tears'] ?? 0,
            'reward_xp' => ($data['reward_tears'] ?? 0) > 0 ? (int) ($data['reward_tears'] * 2) : 50,
            'created_by' => $user->id,
            'ends_at' => $data['ends_at'] ?? now()->addDays(7),
            'status' => 'active',
        ]);

        ChallengeParticipant::query()->create([
            'challenge_id' => $challenge->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        ChallengeParticipant::query()->create([
            'challenge_id' => $challenge->id,
            'user_id' => $data['friend_id'],
            'status' => 'active',
        ]);

        Realtime::publish('challenges.updated', ['user_id' => $user->id]);

        return response()->json(['challenge' => $this->formatChallenge($challenge->fresh(['participants.user.profile', 'creator.profile']), $user->id)]);
    }

    public function accept(Request $request, int $challengeId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $participation = ChallengeParticipant::query()
            ->where('challenge_id', $challengeId)
            ->where('user_id', $user->id)
            ->first();

        if (! $participation) {
            return response()->json(['message' => 'Not invited to this challenge.'], 404);
        }

        $participation->update(['status' => 'active']);

        return response()->json(['ok' => true]);
    }

    public function decline(Request $request, int $challengeId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $participation = ChallengeParticipant::query()
            ->where('challenge_id', $challengeId)
            ->where('user_id', $user->id)
            ->first();

        if (! $participation) {
            return response()->json(['message' => 'Not invited to this challenge.'], 404);
        }

        $participation->update(['status' => 'declined']);

        return response()->json(['ok' => true]);
    }

    public function claim(Request $request, int $challengeId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $participation = ChallengeParticipant::query()
            ->with('challenge')
            ->where('challenge_id', $challengeId)
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNull('reward_claimed_at')
            ->first();

        if (! $participation) {
            return response()->json(['message' => 'No reward to claim.'], 404);
        }

        $challenge = $participation->challenge;

        if ($challenge->reward_tears > 0) {
            WalletController::credit($user->id, $challenge->reward_tears, 'challenge', 'Challenge reward: ' . $challenge->title, $challenge);
        }

        $profile = $user->profile;
        if ($challenge->reward_xp > 0) {
            $oldLevel = (int) ($profile->level ?? 1);
            $profile->increment('xp', $challenge->reward_xp);
            $profile->level = max(1, (int) floor(sqrt($profile->xp / 500)) + 1);
            if ($profile->level > $oldLevel) {
                $profile->skill_points = ((int) ($profile->skill_points ?? 0)) + ($profile->level - $oldLevel);
            }
            $profile->save();
        }

        $participation->update(['reward_claimed_at' => now()]);

        return response()->json([
            'message' => 'Reward claimed!',
            'reward_xp' => $challenge->reward_xp,
            'reward_tears' => $challenge->reward_tears,
            'balance' => $profile->fresh()->tears ?? 0,
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! ($user->is_admin || collect(config('trailbound.admin_emails'))->contains($user->email))) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $challenges = Challenge::query()
            ->withCount('participants')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'title' => $c->title,
                'goal_type' => $c->goal_type,
                'goal_value' => $c->goal_value,
                'reward_tears' => $c->reward_tears,
                'status' => $c->status,
                'participants_count' => $c->participants_count,
                'starts_at' => $c->starts_at,
                'ends_at' => $c->ends_at,
            ]);

        return response()->json(['challenges' => $challenges]);
    }

    private function formatChallenge(Challenge $challenge, int $userId): array
    {
        $myPart = $challenge->participants->firstWhere('user_id', $userId);

        return [
            'id' => $challenge->id,
            'type' => $challenge->type,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'goal_type' => $challenge->goal_type,
            'goal_value' => $challenge->goal_value,
            'goal_label' => $challenge->goal_label,
            'reward_xp' => $challenge->reward_xp,
            'reward_tears' => $challenge->reward_tears,
            'starts_at' => $challenge->starts_at,
            'ends_at' => $challenge->ends_at,
            'status' => $challenge->status,
            'creator' => $challenge->creator ? [
                'id' => $challenge->creator->id,
                'name' => $challenge->creator->name,
                'display_name' => $challenge->creator->profile?->display_name,
                'avatar_path' => $challenge->creator->profile?->avatar_path,
            ] : null,
            'participants' => $challenge->participants->map(fn ($p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user->name,
                'display_name' => $p->user->profile?->display_name,
                'avatar_path' => $p->user->profile?->avatar_path,
                'progress' => $p->progress,
                'status' => $p->status,
                'completed_at' => $p->completed_at,
            ])->values(),
            'my_status' => $myPart?->status ?? null,
            'my_progress' => $myPart?->progress ?? 0,
            'time_remaining' => $challenge->ends_at
                ? max(0, (int) now()->diffInSeconds($challenge->ends_at, false))
                : null,
        ];
    }

    private function goalLabel(string $type, int $value): string
    {
        return match ($type) {
            'distance_km' => "Run {$value} km",
            'streak_days' => "Maintain a {$value}-day streak",
            'runs_count' => "Complete {$value} runs",
            'region_unlock' => "Unlock a new region",
            'quest_complete' => "Complete a quest",
            default => "Achieve {$value}",
        };
    }
}
