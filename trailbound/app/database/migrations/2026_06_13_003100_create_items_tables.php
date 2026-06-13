<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('rarity')->default('common'); // common, magic, rare, epic, legendary
            $table->text('description')->nullable();
            $table->string('type')->default('cosmetic'); // cosmetic, consumable, title, frame, aura, banner
            $table->string('category')->default('general'); // head, cloak, boots, weapon, aura, title, badge, frame
            $table->unsignedInteger('value_tears')->default(0);
            $table->boolean('stackable')->default(false);
            $table->unsignedInteger('max_stack')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('acquired_at')->nullable();
            $table->string('acquired_from')->nullable(); // quest, shop, challenge, admin
            $table->timestamps();
            $table->unique(['user_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_items');
        Schema::dropIfExists('items');
    }
};
