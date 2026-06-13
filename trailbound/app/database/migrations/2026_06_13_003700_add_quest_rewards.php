<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('reward_tears')->default(0)->after('reward_xp');
            $table->foreignId('reward_item_id')->nullable()->after('reward_tears')->constrained('items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['reward_item_id']);
            $table->dropColumn(['reward_tears', 'reward_item_id']);
        });
    }
};
