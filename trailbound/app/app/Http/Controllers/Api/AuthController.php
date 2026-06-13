<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Region;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRegionProgress;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
            'home_area' => ['required', 'string', 'max:120'],
            'runner_type' => ['nullable', 'string', 'max:60'],
            'weekly_goal_km' => ['nullable', 'numeric', 'min:1', 'max:250'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $region = Region::startingFor($data['home_area']);
        $freePackage = Package::query()->where('is_default', true)->first();
        UserProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => $data['name'],
            'home_area' => $data['home_area'],
            'starting_region_id' => $region->id,
            'runner_type' => $data['runner_type'] ?? 'Balanced',
            'weekly_goal_km' => $data['weekly_goal_km'] ?? 15,
            'privacy_level' => 'private',
            'package_id' => $freePackage?->id,
        ]);

        UserRegionProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'region_id' => $region->id],
            ['status' => 'unlocked', 'progress' => 12, 'unlocked_at' => now()]
        );

        return $this->respondWithToken($user->fresh(['profile', 'regionProgress.region', 'taskStates.task.region']));
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower($data['email']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 422);
        }

        return $this->respondWithToken($user->load(['profile', 'regionProgress.region', 'taskStates.task.region']));
    }

    public function logout(): JsonResponse
    {
        return response()->json(['ok' => true])->withCookie(Cookie::create('trailbound_token', '', time() - 3600, '/', null, false, true, false, 'Lax'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['user' => null], 401);
        }

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'home_area' => ['required', 'string', 'max:120'],
            'runner_type' => ['required', 'string', 'max:60'],
            'weekly_goal_km' => ['required', 'numeric', 'min:1', 'max:250'],
            'privacy_level' => ['required', 'in:private,friends,public'],
        ]);

        $region = Region::startingFor($data['home_area']);
        $user->profile()->updateOrCreate(['user_id' => $user->id], $data + ['starting_region_id' => $region->id]);
        UserRegionProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'region_id' => $region->id],
            ['status' => 'unlocked', 'progress' => 12, 'unlocked_at' => now()]
        );

        return response()->json(['user' => $this->userPayload($user->fresh(['profile', 'regionProgress.region', 'taskStates.task.region']))]);
    }

    public function googleStatus(): JsonResponse
    {
        return response()->json([
            'enabled' => false,
            'message' => 'Google sign-in is scaffolded. Add Google OAuth credentials and Socialite before enabling it in production.',
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id], [
            'display_name' => $user->name,
            'home_area' => 'City Bowl',
            'runner_type' => 'Balanced',
            'weekly_goal_km' => 15,
            'privacy_level' => 'private',
        ]);

        $profile->update(['avatar_path' => $path]);

        return response()->json([
            'avatar_url' => Storage::url($path),
            'user' => $this->userPayload($user->fresh(['profile', 'regionProgress.region', 'taskStates.task.region'])),
        ]);
    }

    public function uploadBackground(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'background' => ['required', 'image', 'max:8192'],
        ]);

        $path = $request->file('background')->store('profile-backgrounds', 'public');

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id], [
            'display_name' => $user->name,
            'home_area' => 'City Bowl',
            'runner_type' => 'Balanced',
            'weekly_goal_km' => 15,
            'privacy_level' => 'private',
        ]);

        $profile->update(['background_path' => $path]);

        return response()->json([
            'background_url' => Storage::url($path),
            'user' => $this->userPayload($user->fresh(['profile', 'regionProgress.region', 'taskStates.task.region'])),
        ]);
    }

    public function updateBio(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = $user->profile;
        if ($profile) {
            $profile->update(['bio' => $data['bio']]);
        }

        return response()->json([
            'user' => $this->userPayload($user->fresh('profile')),
        ]);
    }

    private function respondWithToken(User $user): JsonResponse
    {
        $token = Jwt::issue($user);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ])->withCookie(Cookie::create('trailbound_token', $token, time() + (60 * 60 * 24 * 30), '/', null, false, true, false, 'Lax'));
    }

    private function userPayload(User $user): array
    {
        $package = $user->profile?->package_id
            ? \App\Models\Package::query()->find($user->profile->package_id)
            : \App\Models\Package::query()->where('is_default', true)->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) ($user->is_admin || collect(config('trailbound.admin_emails', []))->contains(strtolower($user->email))),
            'profile' => $user->profile,
            'stats' => [
                'level' => $user->profile?->level ?? 1,
                'xp' => $user->profile?->xp ?? 0,
                'total_km' => $user->profile?->total_km ?? 0,
                'runs' => $user->profile?->total_runs ?? 0,
                'tears' => $user->profile?->tears ?? 0,
            ],
            'package' => $package ? [
                'id' => $package->id,
                'key' => $package->key,
                'name' => $package->name,
                'features' => $package->features,
                'limits' => $package->limits,
            ] : null,
        ];
    }
}
