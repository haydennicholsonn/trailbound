<?php

use App\Models\Item;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            if (! Schema::hasColumn('user_items', 'equipped_at')) {
                $table->timestamp('equipped_at')->nullable()->after('acquired_from');
            }
            if (! Schema::hasColumn('user_items', 'market_listed_at')) {
                $table->timestamp('market_listed_at')->nullable()->after('equipped_at');
            }
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            foreach (['room_background_item_id', 'room_floor_item_id', 'room_chair_item_id', 'room_bed_item_id', 'room_decor_item_id'] as $column) {
                if (! Schema::hasColumn('user_profiles', $column)) {
                    $table->foreignId($column)->nullable()->after('background_path')->constrained('items')->nullOnDelete();
                }
            }
            if (! Schema::hasColumn('user_profiles', 'timeout_until')) {
                $table->timestamp('timeout_until')->nullable()->after('lifecycle_stage');
            }
            if (! Schema::hasColumn('user_profiles', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('timeout_until');
            }
            if (! Schema::hasColumn('user_profiles', 'ban_reason')) {
                $table->string('ban_reason')->nullable()->after('banned_at');
            }
            if (! Schema::hasColumn('user_profiles', 'moderation_note')) {
                $table->text('moderation_note')->nullable()->after('ban_reason');
            }
        });

        Schema::create('loot_box_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('box_key')->default('daily_shard');
            $table->string('server_seed_hash');
            $table->string('server_seed');
            $table->string('client_seed');
            $table->unsignedInteger('nonce')->default(1);
            $table->string('roll_hash');
            $table->decimal('roll', 10, 8);
            $table->string('reward_type');
            $table->string('reward_label');
            $table->json('reward_payload')->nullable();
            $table->timestamp('opened_at');
            $table->timestamps();
            $table->index(['user_id', 'box_key', 'opened_at']);
        });

        Schema::create('market_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('price_tears');
            $table->string('status')->default('active');
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->index(['item_id', 'status']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('market_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('price_tears');
            $table->timestamp('sold_at');
            $table->timestamps();
            $table->index(['item_id', 'sold_at']);
        });

        Schema::create('trail_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('visibility')->default('public');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('trail_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trail_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['trail_group_id', 'user_id']);
        });

        Schema::create('trail_group_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trail_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['trail_group_id', 'created_at']);
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'blocked_user_id']);
        });

        $this->seedItems();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('trail_group_messages');
        Schema::dropIfExists('trail_group_members');
        Schema::dropIfExists('trail_groups');
        Schema::dropIfExists('market_sales');
        Schema::dropIfExists('market_listings');
        Schema::dropIfExists('loot_box_opens');

        Schema::table('user_profiles', function (Blueprint $table) {
            foreach (['room_background_item_id', 'room_floor_item_id', 'room_chair_item_id', 'room_bed_item_id', 'room_decor_item_id', 'timeout_until', 'banned_at', 'ban_reason', 'moderation_note'] as $column) {
                if (Schema::hasColumn('user_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('user_items', function (Blueprint $table) {
            foreach (['equipped_at', 'market_listed_at'] as $column) {
                if (Schema::hasColumn('user_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function seedItems(): void
    {
        $items = [
            ['key' => 'zorrin_companion', 'name' => 'Zorrin', 'icon' => 'Eye', 'rarity' => 'legendary', 'description' => 'Your watchful Trailbound companion. Equip him in your room and he reacts to your progress.', 'type' => 'companion', 'category' => 'companion', 'value_tears' => 0],
            ['key' => 'ember_trail_boots', 'name' => 'Ember Trail Boots', 'icon' => 'Zap', 'rarity' => 'rare', 'description' => 'Heat-hazed shoes for runners who turn pavements into routes.', 'type' => 'cosmetic', 'category' => 'boots', 'value_tears' => 42],
            ['key' => 'fynbos_cloak', 'name' => 'Fynbos Cloak', 'icon' => 'Sparkles', 'rarity' => 'magic', 'description' => 'A soft green cloak with Table Mountain wind stitched through it.', 'type' => 'cosmetic', 'category' => 'cloak', 'value_tears' => 34],
            ['key' => 'signal_lantern', 'name' => 'Signal Lantern', 'icon' => 'RadioTower', 'rarity' => 'rare', 'description' => 'Projects a small rally signal around your profile and room.', 'type' => 'cosmetic', 'category' => 'aura', 'value_tears' => 55],
            ['key' => 'stormline_headband', 'name' => 'Stormline Headband', 'icon' => 'Timer', 'rarity' => 'magic', 'description' => 'A clean tempo-piece for runners chasing splits.', 'type' => 'cosmetic', 'category' => 'head', 'value_tears' => 28],
            ['key' => 'city_bowl_chair', 'name' => 'City Bowl Chair', 'icon' => 'Package', 'rarity' => 'common', 'description' => 'A simple room chair unlocked from your first foothold.', 'type' => 'room', 'category' => 'room_chair', 'value_tears' => 18],
            ['key' => 'table_crown_bed', 'name' => 'Table Crown Bed', 'icon' => 'Shield', 'rarity' => 'rare', 'description' => 'A mountain-shadow bed for a proper post-run recovery room.', 'type' => 'room', 'category' => 'room_bed', 'value_tears' => 64],
            ['key' => 'neon_shard_wallpaper', 'name' => 'Neon Shard Wallpaper', 'icon' => 'Star', 'rarity' => 'epic', 'description' => 'Turns your room into a glowing Cape Town shard grid.', 'type' => 'room', 'category' => 'room_background', 'value_tears' => 88],
            ['key' => 'rally_beacon_desk', 'name' => 'Rally Beacon Desk', 'icon' => 'RadioTower', 'rarity' => 'magic', 'description' => 'A compact command desk for group challenges and public rooms.', 'type' => 'room', 'category' => 'room_decor', 'value_tears' => 40],
            ['key' => 'cape_runner_frame', 'name' => 'Cape Runner Frame', 'icon' => 'Camera', 'rarity' => 'rare', 'description' => 'A profile frame with subtle Cape Town contour lines.', 'type' => 'profile', 'category' => 'frame', 'value_tears' => 48],
        ];

        foreach ($items as $item) {
            Item::query()->updateOrCreate(['key' => $item['key']], array_merge($item, [
                'stackable' => false,
                'max_stack' => 1,
                'is_active' => true,
            ]));
        }

        $zorrin = Item::query()->where('key', 'zorrin_companion')->first();
        if ($zorrin) {
            User::query()->pluck('id')->each(function (int $userId) use ($zorrin) {
                UserItem::query()->firstOrCreate(
                    ['user_id' => $userId, 'item_id' => $zorrin->id],
                    ['quantity' => 1, 'acquired_at' => now(), 'acquired_from' => 'founder_companion', 'equipped_at' => now()]
                );
            });
        }

        $roomStarter = Item::query()->whereIn('key', ['city_bowl_chair', 'rally_beacon_desk'])->pluck('id');
        if ($roomStarter->isNotEmpty()) {
            User::query()->pluck('id')->each(function (int $userId) use ($roomStarter) {
                foreach ($roomStarter as $itemId) {
                    UserItem::query()->firstOrCreate(
                        ['user_id' => $userId, 'item_id' => $itemId],
                        ['quantity' => 1, 'acquired_at' => now(), 'acquired_from' => 'starter_room']
                    );
                }
            });
        }

        DB::table('shop_items')->whereIn('item_id', Item::query()->whereIn('key', array_column($items, 'key'))->pluck('id'))->delete();
        Item::query()->whereIn('key', ['ember_trail_boots', 'fynbos_cloak', 'signal_lantern', 'stormline_headband', 'neon_shard_wallpaper', 'table_crown_bed', 'cape_runner_frame'])
            ->get()
            ->each(fn (Item $item) => DB::table('shop_items')->insertOrIgnore([
                'item_id' => $item->id,
                'price_tears' => max(12, (int) $item->value_tears),
                'level_required' => $item->rarity === 'epic' ? 3 : 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }
};
