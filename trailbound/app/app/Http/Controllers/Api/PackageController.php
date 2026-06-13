<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $packages = Package::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Package $pkg) => [
                'id' => $pkg->id,
                'key' => $pkg->key,
                'name' => $pkg->name,
                'price_cents' => $pkg->price_cents,
                'billing_interval' => $pkg->billing_interval,
                'description' => $pkg->description,
                'features' => $pkg->features,
                'limits' => $pkg->limits,
                'is_default' => $pkg->is_default,
            ]);

        return response()->json(['packages' => $packages]);
    }

    public function current(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = $user->profile;
        $package = $profile->package_id ? Package::query()->find($profile->package_id) : Package::query()->where('is_default', true)->first();

        return response()->json([
            'package' => $package ? [
                'id' => $package->id,
                'key' => $package->key,
                'name' => $package->name,
                'price_cents' => $package->price_cents,
                'billing_interval' => $package->billing_interval,
                'description' => $package->description,
                'features' => $package->features,
                'limits' => $package->limits,
            ] : null,
        ]);
    }

    public function select(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
        ]);

        $package = Package::query()->find($data['package_id']);
        if (! $package || ! $package->is_active) {
            return response()->json(['message' => 'Package not available.'], 404);
        }

        if ($package->price_cents > 0) {
            return response()->json([
                'message' => 'Paid packages are coming soon. Payment processing is not yet available.',
            ], 402);
        }

        $user->profile()->update(['package_id' => $package->id]);

        return response()->json([
            'message' => 'Package selected: ' . $package->name,
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
            ],
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! ($user->is_admin || collect(config('trailbound.admin_emails'))->contains($user->email))) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $packages = Package::query()->orderBy('sort_order')->get();

        return response()->json(['packages' => $packages]);
    }

    public function adminStore(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! ($user->is_admin || collect(config('trailbound.admin_emails'))->contains($user->email))) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $data = $request->validate([
            'key' => ['required', 'string', 'unique:packages,key'],
            'name' => ['required', 'string', 'max:200'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'billing_interval' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $package = Package::query()->create($data);

        return response()->json(['package' => $package]);
    }

    public function adminUpdate(Request $request, int $packageId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! ($user->is_admin || collect(config('trailbound.admin_emails'))->contains($user->email))) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        $package = Package::query()->find($packageId);
        if (! $package) {
            return response()->json(['message' => 'Package not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['string', 'max:200'],
            'price_cents' => ['integer', 'min:0'],
            'billing_interval' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $package->update($data);

        return response()->json(['package' => $package->fresh()]);
    }
}
