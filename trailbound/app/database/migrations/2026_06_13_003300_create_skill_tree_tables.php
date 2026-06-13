<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->string('branch'); // endurance, explorer, tempo
            $table->unsignedSmallInteger('tier')->default(1);
            $table->unsignedSmallInteger('position')->default(0); // horizontal position within tier
            $table->string('requirement_type')->default('level'); // level, xp, quest, region, distance
            $table->unsignedInteger('requirement_value')->default(1);
            $table->unsignedInteger('cost_tears')->default(0);
            $table->text('effect')->nullable(); // description of benefit
            $table->string('effect_stat')->nullable(); // stat name
            $table->unsignedInteger('effect_value')->default(0); // stat boost
            $table->json('prerequisite_keys')->nullable(); // array of node keys required before this
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_skill_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_node_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'skill_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_skill_nodes');
        Schema::dropIfExists('skill_nodes');
    }
};
