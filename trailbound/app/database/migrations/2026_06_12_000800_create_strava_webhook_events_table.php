<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strava_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->nullable()->constrained('strava_connections')->nullOnDelete();
            $table->string('object_type');
            $table->unsignedBigInteger('object_id');
            $table->string('aspect_type');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedInteger('event_time');
            $table->jsonb('updates')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['processed_at', 'created_at']);
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strava_webhook_events');
    }
};
