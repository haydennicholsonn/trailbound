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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'runner_type' => ['nullable', 'string', 'max:60'],
            'weekly_goal_km' => ['nullable', 'numeric', 'min:1', 'max:250'],
            'referral_code' => ['nullable', 'string', 'max:120'],
            'package_id' => ['nullable', 'exists:packages,id'],
        ], $this->registrationMessages());

        $region = $this->regionFor((float) $data['lat'], (float) $data['lng']);
        if (! $region) {
            return response()->json([
                'message' => 'Your current location is outside the active Cape Town shard. Try again from Cape Town or wait for the next region rollout.',
            ], 422);
        }

        $referrer = $this->referrerFor($data['referral_code'] ?? null, strtolower($data['email']));
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $package = ! empty($data['package_id'])
            ? Package::query()->where('id', $data['package_id'])->where('is_active', true)->first()
            : Package::query()->where('is_default', true)->first();

        if (! $package) {
            return response()->json(['message' => 'Selected package is not available.'], 422);
        }
        if ($package->price_cents > 0) {
            return response()->json(['message' => 'Paid packages are coming soon. Choose Free for now.'], 422);
        }

        UserProfile::query()->create([
            'user_id' => $user->id,
            'display_name' => $data['name'],
            'friend_code' => $this->generateFriendCode($data['name']),
            'referred_by_user_id' => $referrer?->id,
            'home_area' => $region->name,
            'starting_region_id' => $region->id,
            'runner_type' => $data['runner_type'] ?? 'Pathfinder',
            'weekly_goal_km' => $data['weekly_goal_km'] ?? 15,
            'privacy_level' => 'private',
            'package_id' => $package->id,
        ]);

        UserRegionProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'region_id' => $region->id],
            ['status' => 'unlocked', 'progress' => 12, 'unlocked_at' => now()]
        );

        return $this->respondWithToken($user->fresh(['profile', 'regionProgress.region', 'taskStates.task.region']));
    }

    public function detectShard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $region = $this->regionFor((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'region' => $region ? [
                'id' => $region->id,
                'name' => $region->name,
                'biome' => $region->biome,
                'difficulty' => $region->difficulty,
                'summary' => $region->summary,
            ] : null,
            'message' => $region
                ? "Starting shard detected: {$region->name}."
                : 'Your current position is outside the active Cape Town shard.',
        ]);
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
            'mobile_menu_side' => ['nullable', 'in:left,right'],
            'tutorial_completed_at' => ['nullable', 'date'],
        ]);

        $region = Region::startingFor($data['home_area']);
        $user->profile()->updateOrCreate(['user_id' => $user->id], $data + ['starting_region_id' => $region->id]);
        UserRegionProgress::query()->firstOrCreate(
            ['user_id' => $user->id, 'region_id' => $region->id],
            ['status' => 'unlocked', 'progress' => 12, 'unlocked_at' => now()]
        );

        return response()->json(['user' => $this->userPayload($user->fresh(['profile', 'regionProgress.region', 'taskStates.task.region']))]);
    }

    public function googleRedirect()
    {
        $clientId = config('services.google.client_id');
        if (! $clientId) {
            return response()->json(['message' => 'Google sign-in is not configured.'], 503);
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function googleCallback(Request $request)
    {
        if (! $request->filled('code')) {
            return redirect('/app/?auth=google_failed');
        }

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->string('code')->toString(),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $token->successful()) {
            return redirect('/app/?auth=google_failed');
        }

        $profile = Http::withToken($token->json('access_token'))->get('https://www.googleapis.com/oauth2/v3/userinfo');
        if (! $profile->successful() || ! $profile->json('email')) {
            return redirect('/app/?auth=google_failed');
        }

        $email = strtolower($profile->json('email'));
        $user = User::query()->where('google_id', $profile->json('sub'))->orWhere('email', $email)->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => $profile->json('name') ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $profile->json('sub'),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(40)),
            ]);
        } else {
            $user->forceFill([
                'google_id' => $user->google_id ?: $profile->json('sub'),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }

        $region = Region::query()->where('key', 'city-bowl')->first() ?? Region::query()->orderBy('sort_order')->first();
        $freePackage = Package::query()->where('is_default', true)->first();
        $user->profile()->firstOrCreate(['user_id' => $user->id], [
            'display_name' => $user->name,
            'friend_code' => $this->generateFriendCode($user->name),
            'home_area' => $region?->name ?? 'City Bowl',
            'starting_region_id' => $region?->id,
            'runner_type' => 'Pathfinder',
            'weekly_goal_km' => 15,
            'privacy_level' => 'private',
            'package_id' => $freePackage?->id,
        ]);

        if ($region) {
            UserRegionProgress::query()->firstOrCreate(
                ['user_id' => $user->id, 'region_id' => $region->id],
                ['status' => 'unlocked', 'progress' => 12, 'unlocked_at' => now()]
            );
        }

        return $this->respondWithToken($user->fresh(['profile', 'regionProgress.region', 'taskStates.task.region']))
            ->withHeaders(['Location' => '/app/'])
            ->setStatusCode(302);
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
            'runner_type' => 'Pathfinder',
            'weekly_goal_km' => 15,
            'privacy_level' => 'private',
            'friend_code' => $this->generateFriendCode($user->name),
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
            'runner_type' => 'Pathfinder',
            'weekly_goal_km' => 15,
            'privacy_level' => 'private',
            'friend_code' => $this->generateFriendCode($user->name),
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
        if ($user->profile && ! $user->profile->friend_code) {
            $user->profile->update(['friend_code' => $this->generateFriendCode($user->name)]);
            $user->load('profile');
        }

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

    private function registrationMessages(): array
    {
        return [
            'password.min' => 'Password must be at least 10 characters. Add a few more characters to keep your account safe.',
            'password.letters' => 'Password needs at least one letter.',
            'password.numbers' => 'Password needs at least one number.',
            'email.unique' => 'Email is already registered. Try signing in instead.',
            'lat.required' => 'Location permission is required to assign your starting shard.',
            'lng.required' => 'Location permission is required to assign your starting shard.',
        ];
    }

    private function generateFriendCode(string $name): string
    {
        $base = Str::of($name)->upper()->replaceMatches('/[^A-Z0-9]+/', '')->substr(0, 8)->toString() ?: 'RUNNER';
        do {
            $code = $base . '-' . Str::upper(Str::random(5));
        } while (UserProfile::query()->where('friend_code', $code)->exists());

        return $code;
    }

    private function referrerFor(?string $code, string $email): ?User
    {
        if (! $code) {
            return null;
        }

        $needle = trim($code);
        $profile = UserProfile::query()
            ->where('friend_code', $needle)
            ->orWhereHas('user', fn ($query) => $query
                ->where('email', strtolower($needle))
                ->orWhere('name', $needle))
            ->with('user')
            ->first();

        if (! $profile || strtolower($profile->user?->email ?? '') === $email) {
            return null;
        }

        return $profile->user;
    }

    private function regionFor(float $lat, float $lng): ?Region
    {
        return Region::query()->orderBy('sort_order')->get()->first(function (Region $region) use ($lat, $lng) {
            $ring = $region->polygon['coordinates'][0] ?? null;
            return is_array($ring) && $this->pointInPolygon($lng, $lat, $ring);
        });
    }

    private function pointInPolygon(float $x, float $y, array $ring): bool
    {
        $inside = false;
        $count = count($ring);
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 0.0000001) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }
        return $inside;
    }
}
