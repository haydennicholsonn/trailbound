<?php

use App\Models\Package;
use App\Models\SkillNode;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('user_profiles', 'skill_points')) {
                $table->unsignedInteger('skill_points')->default(0)->after('tears');
            }
            if (! Schema::hasColumn('user_profiles', 'last_respec_at')) {
                $table->timestamp('last_respec_at')->nullable()->after('skill_points');
            }
            if (! Schema::hasColumn('user_profiles', 'respec_count')) {
                $table->unsignedInteger('respec_count')->default(0)->after('last_respec_at');
            }
            if (! Schema::hasColumn('user_profiles', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('notification_preferences');
            }
            if (! Schema::hasColumn('user_profiles', 'lifecycle_stage')) {
                $table->string('lifecycle_stage')->default('new')->after('admin_notes');
            }
        });

        UserProfile::query()->each(function (UserProfile $profile) {
            $spent = DB::table('user_skill_nodes')->where('user_id', $profile->user_id)->count();
            $earned = max(0, ((int) $profile->level) - 1);
            if ((int) $profile->skill_points === 0 && $earned > $spent) {
                $profile->forceFill(['skill_points' => $earned - $spent])->save();
            }
        });

        $adminPackage = Package::query()->updateOrCreate(
            ['key' => 'admin-founder'],
            [
                'name' => 'Admin Founder',
                'price_cents' => 0,
                'billing_interval' => null,
                'description' => 'Internal Trailbound operator package with unrestricted testing access.',
                'features' => [
                    'Full admin control room',
                    'Unlimited friends and challenges',
                    'Early monetisation testing',
                    'CRM and player support tools',
                ],
                'limits' => [
                    'max_friends' => 9999,
                    'max_active_challenges' => 9999,
                    'max_inventory_slots' => 9999,
                    'admin' => true,
                ],
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 99,
            ]
        );

        $hayden = User::query()->where('email', 'haydennicholson98@gmail.com')->first();
        if ($hayden) {
            $hayden->forceFill(['is_admin' => true])->save();
            $profile = $hayden->profile ?: $hayden->profile()->create([
                'display_name' => $hayden->name,
                'home_area' => 'City Bowl',
                'runner_type' => 'Pathfinder',
                'weekly_goal_km' => 15,
                'privacy_level' => 'friends',
            ]);
            $profile->forceFill([
                'tears' => max(100, (int) ($profile->tears ?? 0)),
                'package_id' => $adminPackage->id,
                'lifecycle_stage' => 'founder',
                'admin_notes' => 'Founder/admin testing account.',
                'skill_points' => max((int) ($profile->skill_points ?? 0), max(0, ((int) ($profile->level ?? 1)) - 1)),
            ])->save();
        }

        $nodes = [
            ['endurance-root', 'Steady Feet', 'Timer', 'Endurance', 1, 1, 'level', 1, null, 'Runs over 3km grant +5% XP.', 'run_xp_long', 5],
            ['endurance-rhythm', 'Iron Rhythm', 'Activity', 'Endurance', 2, 1, 'level', 2, ['endurance-root'], '+8% progress toward weekly consistency challenges.', 'weekly_progress', 8],
            ['endurance-long-road', 'Long Road', 'MapPin', 'Endurance', 2, 2, 'distance', 10, ['endurance-root'], 'Runs over 5km push region progress +10%.', 'region_progress_long', 10],
            ['endurance-streakguard', 'Streak Guard', 'Shield', 'Endurance', 3, 1, 'level', 4, ['endurance-rhythm'], 'One weekly streak protection charge per month.', 'streak_protection', 1],
            ['endurance-marathon-heart', 'Marathon Heart', 'Trophy', 'Endurance', 4, 1, 'distance', 42, ['endurance-long-road', 'endurance-streakguard'], '+1 bonus Tear on completed weekly challenges.', 'weekly_tears', 1],

            ['explorer-root', 'Cartographer', 'Map', 'Explorer', 1, 1, 'level', 1, null, '+10% discovery progress in newly visited shards.', 'new_region_progress', 10],
            ['explorer-hidden-trails', 'Hidden Trails', 'Compass', 'Explorer', 2, 1, 'level', 2, ['explorer-root'], 'Quest rewards have a better chance to include items.', 'item_find', 8],
            ['explorer-lorekeeper', 'Lorekeeper', 'BookOpen', 'Explorer', 2, 2, 'level', 3, ['explorer-root'], 'Shard lore and quest hints reveal sooner.', 'lore_reveal', 1],
            ['explorer-wayfinder', 'Wayfinder', 'Navigation', 'Explorer', 3, 1, 'distance', 15, ['explorer-hidden-trails'], 'Map beacons last longer and highlight nearby quests.', 'beacon_duration', 25],
            ['explorer-fogpiercer', 'Fogpiercer', 'Eye', 'Explorer', 4, 1, 'level', 6, ['explorer-lorekeeper', 'explorer-wayfinder'], 'Undiscovered region outlines become clearer.', 'fog_clarity', 1],

            ['tempo-root', 'Quickstep', 'Zap', 'Tempo', 1, 1, 'level', 1, null, '+5% XP when beating your recent average pace.', 'pace_xp', 5],
            ['tempo-negative-split', 'Negative Split', 'Gauge', 'Tempo', 2, 1, 'level', 2, ['tempo-root'], 'Run dashboards highlight stronger closing splits.', 'split_signal', 1],
            ['tempo-storm-pulse', 'Storm Pulse', 'RadioTower', 'Tempo', 2, 2, 'level', 3, ['tempo-root'], '+10% reward value on pace challenges.', 'pace_challenge_reward', 10],
            ['tempo-rally-spark', 'Rally Spark', 'Users', 'Tempo', 3, 1, 'level', 4, ['tempo-storm-pulse'], 'Friend challenges gain bonus visibility in Social.', 'challenge_visibility', 1],
            ['tempo-lightning-line', 'Lightning Line', 'Sparkles', 'Tempo', 4, 1, 'distance', 25, ['tempo-negative-split', 'tempo-rally-spark'], 'Personal-best posts get a boosted Trailbound share card.', 'share_boost', 1],

            ['social-root', 'Kind Signal', 'MessageCircle', 'Social', 1, 1, 'level', 1, null, '+5% bonus XP on friend challenge completions.', 'friend_challenge_xp', 5],
            ['social-crew-link', 'Crew Link', 'Users', 'Social', 2, 1, 'level', 3, ['social-root'], 'See richer friend status and region context.', 'friend_context', 1],
            ['social-shard-captain', 'Shard Captain', 'Star', 'Social', 3, 1, 'level', 5, ['social-crew-link'], 'Region rally beacons feel more prominent to friends.', 'rally_signal', 1],
        ];

        foreach ($nodes as [$key, $name, $icon, $branch, $tier, $position, $requirementType, $requirementValue, $prereqs, $effect, $effectStat, $effectValue]) {
            SkillNode::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'description' => $effect,
                    'branch' => strtolower($branch),
                    'tier' => $tier,
                    'position' => $position,
                    'requirement_type' => $requirementType,
                    'requirement_value' => $requirementValue,
                    'cost_tears' => 0,
                    'effect' => $effect,
                    'effect_stat' => $effectStat,
                    'effect_value' => $effectValue,
                    'prerequisite_keys' => $prereqs,
                    'is_active' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            foreach (['skill_points', 'last_respec_at', 'respec_count', 'admin_notes', 'lifecycle_stage'] as $column) {
                if (Schema::hasColumn('user_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
