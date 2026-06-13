<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedInteger('tears')->default(0)->after('xp');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // earned, spent, top_up, admin_adjust
            $table->unsignedInteger('amount');
            $table->unsignedInteger('balance_after');
            $table->string('source')->nullable(); // quest, challenge, shop, admin, top_up
            $table->nullableMorphs('reference');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('tears');
        });
    }
};
