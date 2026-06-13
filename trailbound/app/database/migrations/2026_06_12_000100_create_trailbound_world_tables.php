<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('biome');
            $table->text('summary');
            $table->string('difficulty')->default('starter');
            $table->unsignedSmallInteger('map_x');
            $table->unsignedSmallInteger('map_y');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('start_keywords')->nullable();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('home_area');
            $table->foreignId('starting_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('runner_type')->default('Balanced');
            $table->unsignedSmallInteger('weekly_goal_km')->default(15);
            $table->string('privacy_level')->default('private');
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('xp')->default(0);
            $table->decimal('total_km', 8, 2)->default(0);
            $table->unsignedInteger('total_runs')->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('unlock_rule');
            $table->string('target_type')->default('distance_km');
            $table->decimal('target_value', 8, 2)->default(2);
            $table->unsignedInteger('reward_xp')->default(100);
            $table->unsignedSmallInteger('unlock_order')->default(1);
            $table->timestamps();
        });

        Schema::create('user_region_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('locked');
            $table->unsignedSmallInteger('progress')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'region_id']);
        });

        Schema::create('user_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('locked');
            $table->unsignedSmallInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'task_id']);
        });

        Schema::create('run_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('distance_km', 8, 2);
            $table->decimal('duration_minutes', 8, 1);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable()->index();
            $table->timestamp('run_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_activities');
        Schema::dropIfExists('user_tasks');
        Schema::dropIfExists('user_region_progress');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('regions');
    }
};
