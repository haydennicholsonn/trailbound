<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function balance(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = $user->profile;

        return response()->json([
            'balance' => $profile->tears ?? 0,
            'transactions' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => $tx->amount,
                    'balance_after' => $tx->balance_after,
                    'source' => $tx->source,
                    'note' => $tx->note,
                    'created_at' => $tx->created_at,
                ]),
        ]);
    }

    public function topUp(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'message' => 'Payment processing is not yet available. Tears top-up will be available soon.',
            'balance' => $user->profile->tears ?? 0,
        ]);
    }

    public static function credit(int $userId, int $amount, string $source, string $note = null, $reference = null): void
    {
        $profile = \App\Models\UserProfile::query()->where('user_id', $userId)->first();
        if (! $profile) return;

        $newBalance = ($profile->tears ?? 0) + $amount;
        $profile->update(['tears' => $newBalance]);

        WalletTransaction::query()->create([
            'user_id' => $userId,
            'type' => 'earned',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'source' => $source,
            'note' => $note,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
        ]);
    }

    public static function debit(int $userId, int $amount, string $source, string $note = null, $reference = null): bool
    {
        $profile = \App\Models\UserProfile::query()->where('user_id', $userId)->first();
        if (! $profile || ($profile->tears ?? 0) < $amount) {
            return false;
        }

        $newBalance = $profile->tears - $amount;
        $profile->update(['tears' => $newBalance]);

        WalletTransaction::query()->create([
            'user_id' => $userId,
            'type' => 'spent',
            'amount' => $amount,
            'balance_after' => $newBalance,
            'source' => $source,
            'note' => $note,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
        ]);

        return true;
    }
}
