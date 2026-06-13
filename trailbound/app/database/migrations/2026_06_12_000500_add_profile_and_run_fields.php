<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
            $table->text('bio')->nullable();
        });

        Schema::table('run_activities', function (Blueprint $table) {
            $table->text('polyline')->nullable();
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->jsonb('image_paths')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('run_activities', function (Blueprint $table) {
            $table->dropColumn(['image_paths', 'start_lng', 'start_lat', 'polyline']);
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['bio', 'avatar_path']);
        });
    }
};
