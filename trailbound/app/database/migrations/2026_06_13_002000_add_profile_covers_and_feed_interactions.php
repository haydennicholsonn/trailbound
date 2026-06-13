<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('background_path')->nullable()->after('avatar_path');
        });

        Schema::create('feed_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_event_id')->constrained('activity_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->timestamps();
            $table->unique(['activity_event_id', 'user_id']);
            $table->index(['activity_event_id', 'kind']);
        });

        Schema::create('feed_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_event_id')->constrained('activity_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['activity_event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_comments');
        Schema::dropIfExists('feed_reactions');

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('background_path');
        });
    }
};
