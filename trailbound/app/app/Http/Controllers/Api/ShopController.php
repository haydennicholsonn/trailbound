<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ShopItem;
use App\Models\UserItem;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = $user->profile;
        $userItemIds = UserItem::query()->where('user_id', $user->id)->pluck('item_id');

        $shopItems = ShopItem::query()
            ->with('item')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ShopItem $shopItem) => [
                'id' => $shopItem->id,
                'item' => [
                    'id' => $shopItem->item->id,
                    'name' => $shopItem->item->name,
                    'icon' => $shopItem->item->icon,
                    'rarity' => $shopItem->item->rarity,
                    'description' => $shopItem->item->description,
                    'type' => $shopItem->item->type,
                    'category' => $shopItem->item->category,
                ],
                'price_tears' => $shopItem->price_tears,
                'stock' => $shopItem->stock,
                'level_required' => $shopItem->level_required,
                'is_featured' => $shopItem->is_featured,
                'owned' => $userItemIds->contains($shopItem->item_id),
                'unlocked' => ($profile->level ?? 1) >= $shopItem->level_required,
            ]);

        return response()->json([
            'shop_items' => $shopItems,
            'balance' => $profile->tears ?? 0,
        ]);
    }

    public function buy(Request $request, int $shopItemId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $shopItem = ShopItem::query()->with('item')->find($shopItemId);
        if (! $shopItem || ! $shopItem->is_active) {
            return response()->json(['message' => 'Item not found in shop.'], 404);
        }

        $profile = $user->profile;
        if (($profile->level ?? 1) < $shopItem->level_required) {
            return response()->json(['message' => 'You need to be level ' . $shopItem->level_required . ' to purchase this item.'], 403);
        }

        $alreadyOwned = UserItem::query()->where('user_id', $user->id)->where('item_id', $shopItem->item_id)->exists();
        if ($alreadyOwned && ! $shopItem->item->stackable) {
            return response()->json(['message' => 'You already own this item.'], 409);
        }

        $tears = $profile->tears ?? 0;
        if ($tears < $shopItem->price_tears) {
            return response()->json([
                'message' => 'Not enough Tears. You need ' . $shopItem->price_tears . ' Tears, but you have ' . $tears . '.',
                'balance' => $tears,
                'required' => $shopItem->price_tears,
            ], 402);
        }

        $success = WalletController::debit($user->id, $shopItem->price_tears, 'shop', 'Purchased ' . $shopItem->item->name, $shopItem);
        if (! $success) {
            return response()->json(['message' => 'Transaction failed.'], 500);
        }

        $userItem = UserItem::query()->where('user_id', $user->id)->where('item_id', $shopItem->item_id)->first();
        if ($userItem && $shopItem->item->stackable) {
            $userItem->increment('quantity');
        } else {
            UserItem::query()->create([
                'user_id' => $user->id,
                'item_id' => $shopItem->item_id,
                'quantity' => 1,
                'acquired_at' => now(),
                'acquired_from' => 'shop',
            ]);
        }

        if ($shopItem->stock !== null) {
            $shopItem->decrement('stock');
        }

        $newBalance = $user->profile->fresh()->tears ?? 0;

        return response()->json([
            'message' => 'Purchased ' . $shopItem->item->name . '!',
            'balance' => $newBalance,
            'item' => [
                'id' => $shopItem->item->id,
                'name' => $shopItem->item->name,
                'icon' => $shopItem->item->icon,
                'rarity' => $shopItem->item->rarity,
            ],
        ]);
    }
}
