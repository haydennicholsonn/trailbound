<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrailGroup;
use App\Models\TrailGroupMember;
use App\Models\TrailGroupMessage;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Jwt;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $groups = TrailGroup::query()
            ->withCount(['members as member_count' => fn ($query) => $query->where('status', 'active'), 'messages'])
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('visibility', 'public')
                    ->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id)->where('status', 'active'));
            })
            ->latest()
            ->get()
            ->map(fn (TrailGroup $group) => $this->groupPayload($group, $user->id));

        return response()->json(['groups' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || $this->restricted($user)) {
            return response()->json(['message' => 'You cannot create groups right now.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:600'],
            'visibility' => ['required', 'in:public,private'],
            'icon' => ['nullable', 'string', 'max:40'],
        ]);

        $slug = Str::slug($data['name']) ?: 'trail-group';
        $base = $slug;
        $i = 2;
        while (TrailGroup::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $group = TrailGroup::query()->create([
            'owner_id' => $user->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'],
            'icon' => $data['icon'] ?? 'Users',
        ]);
        TrailGroupMember::query()->create(['trail_group_id' => $group->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

        return response()->json(['message' => 'Group created.', 'group' => $this->groupPayload($group->loadCount('members', 'messages'), $user->id)]);
    }

    public function show(Request $request, int $groupId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $group = TrailGroup::query()->with(['members.user.profile', 'messages.user.profile'])->withCount('members', 'messages')->find($groupId);
        if (! $group || ! $this->canView($group, $user->id)) {
            return response()->json(['message' => 'Group not found.'], 404);
        }

        return response()->json([
            'group' => $this->groupPayload($group, $user->id),
            'members' => $group->members->map(fn (TrailGroupMember $member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user->profile?->display_name ?: $member->user->name,
                'avatar_path' => $member->user->profile?->avatar_path,
                'role' => $member->role,
                'status' => $member->status,
            ]),
            'messages' => $group->messages->sortBy('created_at')->values()->take(-80)->map(fn (TrailGroupMessage $message) => $this->messagePayload($message)),
        ]);
    }

    public function join(Request $request, int $groupId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        $group = TrailGroup::query()->find($groupId);
        if (! $user || ! $group || $this->restricted($user)) {
            return response()->json(['message' => 'Group not found.'], 404);
        }
        if ($group->visibility !== 'public') {
            return response()->json(['message' => 'This group is invite-only.'], 403);
        }

        $member = TrailGroupMember::query()->updateOrCreate(
            ['trail_group_id' => $group->id, 'user_id' => $user->id],
            ['role' => 'member', 'status' => 'active', 'joined_at' => now()]
        );

        return response()->json(['message' => 'Joined group.', 'member' => $member]);
    }

    public function addMember(Request $request, int $groupId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        $group = TrailGroup::query()->find($groupId);
        if (! $user || ! $group || ! $this->canModerate($groupId, $user->id)) {
            return response()->json(['message' => 'Group admin access required.'], 403);
        }

        $data = $request->validate(['email' => ['required', 'email']]);
        $target = User::query()->where('email', strtolower($data['email']))->first();
        if (! $target) {
            return response()->json(['message' => 'No user with that email.'], 404);
        }

        TrailGroupMember::query()->updateOrCreate(
            ['trail_group_id' => $groupId, 'user_id' => $target->id],
            ['role' => 'member', 'status' => 'active', 'joined_at' => now()]
        );

        return response()->json(['message' => 'Member added.']);
    }

    public function removeMember(Request $request, int $groupId, int $memberId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! $this->canModerate($groupId, $user->id)) {
            return response()->json(['message' => 'Group admin access required.'], 403);
        }

        TrailGroupMember::query()->where('trail_group_id', $groupId)->where('id', $memberId)->where('role', '!=', 'owner')->delete();
        return response()->json(['message' => 'Member removed.']);
    }

    public function blockMember(Request $request, int $groupId, int $memberId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! $this->canModerate($groupId, $user->id)) {
            return response()->json(['message' => 'Group admin access required.'], 403);
        }

        TrailGroupMember::query()->where('trail_group_id', $groupId)->where('id', $memberId)->where('role', '!=', 'owner')->update(['status' => 'blocked']);
        return response()->json(['message' => 'Member blocked from group.']);
    }

    public function messages(Request $request, int $groupId): JsonResponse
    {
        return $this->show($request, $groupId);
    }

    public function send(Request $request, int $groupId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || $this->restricted($user) || ! $this->activeMember($groupId, $user->id)) {
            return response()->json(['message' => 'Join this group before posting.'], 403);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:1200']]);
        $message = TrailGroupMessage::query()->create(['trail_group_id' => $groupId, 'user_id' => $user->id, 'body' => $data['body']]);
        Realtime::publish('groups.updated', ['group_id' => $groupId]);

        return response()->json(['message' => $this->messagePayload($message->fresh('user.profile'))]);
    }

    public function blockUser(Request $request, int $userId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || $user->id === $userId) {
            return response()->json(['message' => 'Cannot block this user.'], 422);
        }
        UserBlock::query()->updateOrCreate(['user_id' => $user->id, 'blocked_user_id' => $userId], ['reason' => $request->input('reason')]);
        return response()->json(['message' => 'User blocked.']);
    }

    public function unblockUser(Request $request, int $userId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if ($user) {
            UserBlock::query()->where('user_id', $user->id)->where('blocked_user_id', $userId)->delete();
        }
        return response()->json(['message' => 'User unblocked.']);
    }

    private function groupPayload(TrailGroup $group, int $viewerId): array
    {
        $member = TrailGroupMember::query()->where('trail_group_id', $group->id)->where('user_id', $viewerId)->first();
        $last = TrailGroupMessage::query()->where('trail_group_id', $group->id)->latest()->first();
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'visibility' => $group->visibility,
            'icon' => $group->icon,
            'member_count' => $group->member_count ?? $group->members()->where('status', 'active')->count(),
            'messages_count' => $group->messages_count ?? $group->messages()->count(),
            'my_role' => $member?->role,
            'my_status' => $member?->status,
            'joined' => $member?->status === 'active',
            'can_moderate' => in_array($member?->role, ['owner', 'admin'], true),
            'last_message' => $last?->body,
            'last_message_at' => $last?->created_at,
        ];
    }

    private function messagePayload(TrailGroupMessage $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'created_at' => $message->created_at,
            'user' => [
                'id' => $message->user->id,
                'name' => $message->user->profile?->display_name ?: $message->user->name,
                'avatar_path' => $message->user->profile?->avatar_path,
            ],
        ];
    }

    private function canView(TrailGroup $group, int $userId): bool
    {
        return $group->visibility === 'public' || $this->activeMember($group->id, $userId);
    }

    private function activeMember(int $groupId, int $userId): bool
    {
        return TrailGroupMember::query()->where('trail_group_id', $groupId)->where('user_id', $userId)->where('status', 'active')->exists();
    }

    private function canModerate(int $groupId, int $userId): bool
    {
        return TrailGroupMember::query()
            ->where('trail_group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }

    private function restricted(User $user): bool
    {
        return (bool) $user->profile?->banned_at || ($user->profile?->timeout_until && $user->profile->timeout_until->isFuture());
    }
}
