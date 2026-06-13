<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('billing_interval')->nullable(); // monthly, yearly, one_time
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // array of feature strings
            $table->json('limits')->nullable(); // { max_friends, max_challenges, etc }
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('privacy_level')->constrained('packages')->nullOnDelete();
        });

        DB::table('packages')->insert([
            'key' => 'free',
            'name' => 'Free Runner',
            'price_cents' => 0,
            'billing_interval' => null,
            'description' => 'The essential Trailbound experience. Explore Cape Town, complete quests, and connect with fellow runners.',
            'features' => json_encode([
                'Full Cape Town map access',
                'Region discovery & progression',
                'Quest system (all regions)',
                'Friend connections (up to 20)',
                'Basic inventory & items',
                'Tears wallet & rewards',
                'Social feed & posts',
                'Profile customization',
                'Daily challenges',
            ]),
            'limits' => json_encode([
                'max_friends' => 20,
                'max_active_challenges' => 3,
                'max_inventory_slots' => 50,
                'daily_challenge_entries' => 1,
            ]),
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');
        });
        Schema::dropIfExists('packages');
    }
};
