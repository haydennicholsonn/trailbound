<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('friend_code', 24)->nullable()->unique()->after('display_name');
            $table->foreignId('referred_by_user_id')->nullable()->after('friend_code')->constrained('users')->nullOnDelete();
            $table->timestamp('tutorial_completed_at')->nullable()->after('package_id');
            $table->string('mobile_menu_side', 12)->default('right')->after('tutorial_completed_at');
            $table->json('notification_preferences')->nullable()->after('mobile_menu_side');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn([
                'friend_code',
                'referred_by_user_id',
                'tutorial_completed_at',
                'mobile_menu_side',
                'notification_preferences',
            ]);
        });
    }
};
