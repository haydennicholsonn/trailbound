<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\MarketListing;
use App\Models\MarketSale;
use App\Models\UserItem;
use App\Support\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $listings = MarketListing::query()
            ->with(['item', 'seller.profile'])
            ->where('status', 'active')
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (MarketListing $listing) => $this->listingPayload($listing, $user->id));

        $myItems = UserItem::query()
            ->with('item')
            ->where('user_id', $user->id)
            ->whereNull('market_listed_at')
            ->whereHas('item', fn ($query) => $query->where('key', '!=', 'zorrin_companion'))
            ->get()
            ->map(fn (UserItem $userItem) => [
                'id' => $userItem->id,
                'name' => $userItem->item->name,
                'rarity' => $userItem->item->rarity,
                'icon' => $userItem->item->icon,
                'value_tears' => $userItem->item->value_tears,
            ]);

        $salesTrend = MarketSale::query()
            ->select(DB::raw('DATE(sold_at) as day'), DB::raw('count(*) as sales'), DB::raw('sum(price_tears) as volume'))
            ->where('sold_at', '>=', now()->subDays(14))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'balance' => $user->profile?->tears ?? 0,
            'listings' => $listings,
            'my_items' => $myItems,
            'sales_trend' => $salesTrend,
            'recent_sales' => MarketSale::query()->with('item')->latest('sold_at')->limit(12)->get()->map(fn (MarketSale $sale) => [
                'item' => $sale->item?->name,
                'price_tears' => $sale->price_tears,
                'sold_at' => $sale->sold_at,
            ]),
        ]);
    }

    public function listItem(Request $request): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'user_item_id' => ['required', 'integer', 'exists:user_items,id'],
            'price_tears' => ['required', 'integer', 'min:1', 'max:50000'],
        ]);

        $userItem = UserItem::query()->with('item')->where('user_id', $user->id)->whereNull('market_listed_at')->find($data['user_item_id']);
        if (! $userItem || $userItem->item->key === 'zorrin_companion') {
            return response()->json(['message' => 'That item cannot be listed.'], 422);
        }

        $listing = MarketListing::query()->create([
            'user_item_id' => $userItem->id,
            'item_id' => $userItem->item_id,
            'seller_id' => $user->id,
            'price_tears' => $data['price_tears'],
            'status' => 'active',
        ]);
        $userItem->update(['market_listed_at' => now(), 'equipped_at' => null]);

        return response()->json(['message' => 'Item listed on the market.', 'listing' => $this->listingPayload($listing->fresh(['item', 'seller.profile']), $user->id)]);
    }

    public function buy(Request $request, int $listingId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user || ! $user->profile) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return DB::transaction(function () use ($listingId, $user) {
            $listing = MarketListing::query()->with(['item', 'userItem'])->where('status', 'active')->lockForUpdate()->find($listingId);
            if (! $listing) {
                return response()->json(['message' => 'Listing not found.'], 404);
            }
            if ($listing->seller_id === $user->id) {
                return response()->json(['message' => 'You already own this listing.'], 422);
            }
            if (! $listing->item->stackable && UserItem::query()->where('user_id', $user->id)->where('item_id', $listing->item_id)->exists()) {
                return response()->json(['message' => 'You already own this unique item.'], 422);
            }
            if (! WalletController::debit($user->id, $listing->price_tears, 'market_buy', "Bought {$listing->item->name}", $listing)) {
                return response()->json(['message' => 'Not enough Tears.'], 422);
            }
            WalletController::credit($listing->seller_id, $listing->price_tears, 'market_sale', "Sold {$listing->item->name}", $listing);

            $existing = UserItem::query()->where('user_id', $user->id)->where('item_id', $listing->item_id)->first();
            if ($existing && $listing->item->stackable) {
                $existing->increment('quantity', $listing->userItem->quantity);
                $listing->userItem->delete();
            } else {
                $listing->userItem->update(['user_id' => $user->id, 'market_listed_at' => null, 'acquired_from' => 'market']);
            }

            $listing->update(['status' => 'sold', 'buyer_id' => $user->id, 'sold_at' => now()]);
            MarketSale::query()->create([
                'market_listing_id' => $listing->id,
                'item_id' => $listing->item_id,
                'seller_id' => $listing->seller_id,
                'buyer_id' => $user->id,
                'price_tears' => $listing->price_tears,
                'sold_at' => now(),
            ]);

            return response()->json(['message' => "Bought {$listing->item->name}.", 'balance' => $user->profile->fresh()->tears ?? 0]);
        });
    }

    public function cancel(Request $request, int $listingId): JsonResponse
    {
        $user = Jwt::userFromRequest($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $listing = MarketListing::query()->with('userItem')->where('seller_id', $user->id)->where('status', 'active')->find($listingId);
        if (! $listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }
        $listing->update(['status' => 'cancelled']);
        $listing->userItem?->update(['market_listed_at' => null]);

        return response()->json(['message' => 'Listing cancelled.']);
    }

    private function listingPayload(MarketListing $listing, int $viewerId): array
    {
        return [
            'id' => $listing->id,
            'price_tears' => $listing->price_tears,
            'status' => $listing->status,
            'mine' => $listing->seller_id === $viewerId,
            'seller' => $listing->seller?->profile?->display_name ?: $listing->seller?->name,
            'created_at' => $listing->created_at,
            'item' => [
                'id' => $listing->item?->id,
                'name' => $listing->item?->name,
                'icon' => $listing->item?->icon,
                'rarity' => $listing->item?->rarity,
                'description' => $listing->item?->description,
                'category' => $listing->item?->category,
            ],
        ];
    }
}
