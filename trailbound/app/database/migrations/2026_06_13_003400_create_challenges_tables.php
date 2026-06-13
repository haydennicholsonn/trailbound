<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // official_daily, official_weekly, official_monthly, friend
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('goal_type'); // distance_km, streak_days, runs_count, region_unlock, quest_complete
            $table->unsignedInteger('goal_value')->default(1);
            $table->string('goal_label')->nullable();
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_tears')->default(0);
            $table->foreignId('reward_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quest_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('active'); // active, completed, expired, cancelled
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence')->nullable(); // daily, weekly, monthly
            $table->timestamps();
        });

        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('progress')->default(0);
            $table->string('status')->default('active'); // active, completed, claimed, declined
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reward_claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['challenge_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenges');
    }
};
