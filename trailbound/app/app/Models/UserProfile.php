<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'display_name', 'friend_code', 'referred_by_user_id', 'home_area', 'starting_region_id', 'runner_type', 'weekly_goal_km', 'privacy_level', 'level', 'xp', 'total_km', 'total_runs', 'avatar_path', 'background_path', 'room_background_item_id', 'room_floor_item_id', 'room_chair_item_id', 'room_bed_item_id', 'room_decor_item_id', 'bio', 'package_id', 'tutorial_completed_at', 'mobile_menu_side', 'notification_preferences', 'skill_points', 'last_respec_at', 'respec_count', 'admin_notes', 'lifecycle_stage', 'timeout_until', 'banned_at', 'ban_reason', 'moderation_note'])]
class UserProfile extends Model
{
    protected function casts(): array
    {
        return [
            'tutorial_completed_at' => 'datetime',
            'notification_preferences' => 'array',
            'last_respec_at' => 'datetime',
            'timeout_until' => 'datetime',
            'banned_at' => 'datetime',
        ];
    }

    public function getAvatarPathAttribute(?string $value): ?string
    {
        return $this->publicMediaPath($value);
    }

    public function getBackgroundPathAttribute(?string $value): ?string
    {
        return $this->publicMediaPath($value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startingRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'starting_region_id');
    }

    private function publicMediaPath(?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'http') || str_starts_with($path, '/storage/')) {
            return $path;
        }

        return '/storage/' . ltrim($path, '/');
    }
}
