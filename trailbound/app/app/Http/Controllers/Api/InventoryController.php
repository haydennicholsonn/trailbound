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
            ->map(fn (UserItem $userItem) => [
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
                'acquired_at' => $userItem->acquired_at,
                'acquired_from' => $userItem->acquired_from,
            ]);

        return response()->json(['items' => $items]);
    }
}
