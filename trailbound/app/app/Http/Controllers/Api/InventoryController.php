<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserItem;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $items = UserItem::query()
            ->with('item')
            ->where('user_id', $user->id)
            ->latest('acquired_at')
            ->get()
            ->map(fn (UserItem $userItem) => $this->payload($userItem));

        $roomIds = collect([
            'background' => $user->profile?->room_background_item_id,
            'floor' => $user->profile?->room_floor_item_id,
            'chair' => $user->profile?->room_chair_item_id,
            'bed' => $user->profile?->room_bed_item_id,
            'decor' => $user->profile?->room_decor_item_id,
        ])->filter();

        return response()->json([
            'items' => $items,
            'room' => [
                'background_item_id' => $user->profile?->room_background_item_id,
                'floor_item_id' => $user->profile?->room_floor_item_id,
                'chair_item_id' => $user->profile?->room_chair_item_id,
                'bed_item_id' => $user->profile?->room_bed_item_id,
                'decor_item_id' => $user->profile?->room_decor_item_id,
                'equipped_count' => $roomIds->count(),
            ],
        ]);
    }

    public function equip(Request $request, int $userItemId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userItem = UserItem::query()
            ->with('item')
            ->where('user_id', $user->id)
            ->whereNull('market_listed_at')
            ->find($userItemId);

        if (! $userItem) {
            return response()->json(['message' => 'Item not found or currently listed on the market.'], 404);
        }

        $category = $userItem->item->category;
        $roomMap = [
            'room_background' => 'room_background_item_id',
            'room_floor' => 'room_floor_item_id',
            'room_chair' => 'room_chair_item_id',
            'room_bed' => 'room_bed_item_id',
            'room_decor' => 'room_decor_item_id',
        ];

        if (isset($roomMap[$category]) && $user->profile) {
            $user->profile->update([$roomMap[$category] => $userItem->item_id]);
        }

        UserItem::query()
            ->where('user_id', $user->id)
            ->whereHas('item', fn ($query) => $query->where('category', $category))
            ->update(['equipped_at' => null]);

        $userItem->update(['equipped_at' => now()]);

        return response()->json([
            'message' => "{$userItem->item->name} equipped.",
            'item' => $this->payload($userItem->fresh('item')),
        ]);
    }

    private function payload(UserItem $userItem): array
    {
        return [
            'id' => $userItem->id,
            'item_id' => $userItem->item_id,
            'name' => $userItem->item->name,
            'icon' => $userItem->item->icon,
            'rarity' => $userItem->item->rarity,
            'description' => $userItem->item->description,
            'type' => $userItem->item->type,
            'category' => $userItem->item->category,
            'quantity' => $userItem->quantity,
            'stackable' => $userItem->item->stackable,
            'equipped' => (bool) $userItem->equipped_at,
            'market_listed' => (bool) $userItem->market_listed_at,
            'acquired_at' => $userItem->acquired_at,
            'acquired_from' => $userItem->acquired_from,
        ];
    }
}
