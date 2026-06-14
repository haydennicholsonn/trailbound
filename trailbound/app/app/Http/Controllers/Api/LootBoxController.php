<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\LootBoxOpen;
use App\Models\UserItem;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LootBoxController extends Controller
{
    public function daily(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $todayOpen = LootBoxOpen::query()
            ->where('user_id', $user->id)
            ->where('box_key', 'daily_shard')
            ->whereDate('opened_at', today())
            ->latest('opened_at')
            ->first();

        $latest = LootBoxOpen::query()
            ->where('user_id', $user->id)
            ->where('box_key', 'daily_shard')
            ->latest('opened_at')
            ->first();

        return response()->json([
            'can_open' => ! $todayOpen,
            'opened_today' => $todayOpen ? $this->payload($todayOpen) : null,
            'latest' => $latest ? $this->payload($latest) : null,
            'weights' => [
                ['reward' => 'item', 'weight' => 55],
                ['reward' => 'tears', 'weight' => 20],
                ['reward' => 'room', 'weight' => 15],
                ['reward' => 'skill_point', 'weight' => 10],
            ],
        ]);
    }

    public function openDaily(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! $user->profile) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (LootBoxOpen::query()->where('user_id', $user->id)->where('box_key', 'daily_shard')->whereDate('opened_at', today())->exists()) {
            return response()->json(['message' => 'Daily box already opened. Come back tomorrow.'], 422);
        }

        $clientSeed = trim((string) $request->input('client_seed', "trailbound-{$user->id}-" . today()->toDateString()));
        $serverSeed = Str::random(64);
        $nonce = LootBoxOpen::query()->where('user_id', $user->id)->count() + 1;
        $hash = hash('sha256', "{$serverSeed}:{$clientSeed}:{$nonce}");
        $roll = hexdec(substr($hash, 0, 8)) / 0xffffffff;
        [$rewardType, $rewardLabel, $rewardPayload] = $this->grantReward($user, $roll);

        $open = LootBoxOpen::query()->create([
            'user_id' => $user->id,
            'box_key' => 'daily_shard',
            'server_seed_hash' => hash('sha256', $serverSeed),
            'server_seed' => $serverSeed,
            'client_seed' => $clientSeed,
            'nonce' => $nonce,
            'roll_hash' => $hash,
            'roll' => $roll,
            'reward_type' => $rewardType,
            'reward_label' => $rewardLabel,
            'reward_payload' => $rewardPayload,
            'opened_at' => now(),
        ]);

        $reel = Item::query()->where('is_active', true)->inRandomOrder()->limit(18)->get()
            ->map(fn (Item $item) => ['name' => $item->name, 'icon' => $item->icon, 'rarity' => $item->rarity])
            ->push(['name' => $rewardLabel, 'icon' => $rewardPayload['icon'] ?? 'Gem', 'rarity' => $rewardPayload['rarity'] ?? 'magic'])
            ->values();

        return response()->json([
            'message' => "Daily box opened: {$rewardLabel}",
            'open' => $this->payload($open),
            'reel' => $reel,
            'balance' => $user->profile->fresh()->tears ?? 0,
            'skill_points' => $user->profile->fresh()->skill_points ?? 0,
        ]);
    }

    private function grantReward($user, float $roll): array
    {
        if ($roll < 0.10) {
            $user->profile->update(['skill_points' => (int) $user->profile->skill_points + 1]);
            return ['skill_point', '+1 Skill Point', ['icon' => 'Sword', 'rarity' => 'epic']];
        }

        if ($roll < 0.30) {
            $amount = 5 + (int) floor(($roll - 0.10) / 0.20 * 31);
            WalletController::credit($user->id, $amount, 'daily_loot', 'Daily shard box');
            return ['tears', "+{$amount} Tears", ['amount' => $amount, 'icon' => 'Droplet', 'rarity' => 'magic']];
        }

        $roomOnly = $roll < 0.45;
        $query = Item::query()->where('is_active', true);
        if ($roomOnly) {
            $query->where('type', 'room');
        } else {
            $query->where('key', '!=', 'zorrin_companion');
        }

        $items = $query->get();
        $item = $items->isNotEmpty() ? $this->weightedItem($items) : Item::query()->where('key', 'city_bowl_chair')->first();

        if ($item) {
            $existing = UserItem::query()->where('user_id', $user->id)->where('item_id', $item->id)->first();
            if ($existing && $item->stackable) {
                $existing->increment('quantity');
            } elseif (! $existing) {
                UserItem::query()->create(['user_id' => $user->id, 'item_id' => $item->id, 'quantity' => 1, 'acquired_at' => now(), 'acquired_from' => 'daily_loot']);
            } else {
                WalletController::credit($user->id, max(3, (int) floor($item->value_tears / 3)), 'duplicate_item', "Duplicate {$item->name}");
            }

            return [$roomOnly ? 'room' : 'item', $item->name, ['item_id' => $item->id, 'icon' => $item->icon, 'rarity' => $item->rarity]];
        }

        WalletController::credit($user->id, 10, 'daily_loot', 'Fallback daily shard reward');
        return ['tears', '+10 Tears', ['amount' => 10, 'icon' => 'Droplet', 'rarity' => 'common']];
    }

    private function weightedItem($items): Item
    {
        $weights = ['common' => 45, 'magic' => 28, 'rare' => 17, 'epic' => 8, 'legendary' => 2];
        $pool = $items->flatMap(fn (Item $item) => array_fill(0, $weights[$item->rarity] ?? 10, $item->id))->values();
        $id = $pool[random_int(0, max(0, $pool->count() - 1))];
        return $items->firstWhere('id', $id) ?: $items->first();
    }

    private function payload(LootBoxOpen $open): array
    {
        return [
            'id' => $open->id,
            'server_seed_hash' => $open->server_seed_hash,
            'server_seed' => $open->server_seed,
            'client_seed' => $open->client_seed,
            'nonce' => $open->nonce,
            'roll_hash' => $open->roll_hash,
            'roll' => $open->roll,
            'reward_type' => $open->reward_type,
            'reward_label' => $open->reward_label,
            'reward_payload' => $open->reward_payload,
            'opened_at' => $open->opened_at,
        ];
    }
}
