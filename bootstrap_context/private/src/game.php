<?php

declare(strict_types=1);

function trailbound_level_xp_required(int $level): int
{
    return 500 + ($level * $level * 75);
}

function trailbound_normalize_strava_activity(array $activity): array
{
    $distanceM = (float) ($activity['distance'] ?? 0);
    $movingTime = (int) ($activity['moving_time'] ?? 0);
    $distanceKm = $distanceM / 1000;

    return [
        'provider_activity_id' => (string) ($activity['id'] ?? ''),
        'activity_type' => (string) ($activity['sport_type'] ?? $activity['type'] ?? 'Run'),
        'name' => (string) ($activity['name'] ?? 'Strava Run'),
        'distance_m' => $distanceM,
        'moving_time_s' => $movingTime,
        'elapsed_time_s' => isset($activity['elapsed_time']) ? (int) $activity['elapsed_time'] : null,
        'average_speed_mps' => isset($activity['average_speed']) ? (float) $activity['average_speed'] : null,
        'average_pace_sec_per_km' => $distanceKm > 0 ? (int) round($movingTime / $distanceKm) : null,
        'start_date' => date('Y-m-d H:i:s', strtotime((string) ($activity['start_date'] ?? 'now'))),
        'timezone' => $activity['timezone'] ?? null,
        'summary_polyline' => $activity['map']['summary_polyline'] ?? null,
        'start_lat' => $activity['start_latlng'][0] ?? null,
        'start_lng' => $activity['start_latlng'][1] ?? null,
        'end_lat' => $activity['end_latlng'][0] ?? null,
        'end_lng' => $activity['end_latlng'][1] ?? null,
        'raw_json' => json_encode($activity, JSON_UNESCAPED_SLASHES),
    ];
}

function trailbound_validate_activity(array $activity): array
{
    if ($activity['distance_m'] < 500) {
        return ['ignored', 'Distance below 500m minimum.'];
    }

    if ($activity['moving_time_s'] < 180) {
        return ['ignored', 'Moving time below 3 minute minimum.'];
    }

    if ($activity['average_speed_mps'] !== null && $activity['average_speed_mps'] > 8) {
        return ['flagged', 'Average speed is above normal running validation.'];
    }

    return ['valid', null];
}

function trailbound_base_activity_xp(array $activity): int
{
    $distanceXp = (int) floor(($activity['distance_m'] / 1000) * 100);
    $timeBonus = min(50, (int) floor($activity['moving_time_s'] / 60));

    return $distanceXp + $timeBonus;
}

function trailbound_apply_xp(PDO $db, int $userId, int $xp): array
{
    if ($xp <= 0) {
        return ['level_ups' => 0, 'level' => null];
    }

    $statement = $db->prepare('SELECT level, xp_total, xp_current_level, skill_points FROM users WHERE id = :user_id LIMIT 1');
    $statement->execute(['user_id' => $userId]);
    $user = $statement->fetch();

    if (!$user) {
        return ['level_ups' => 0, 'level' => null];
    }

    $level = (int) $user['level'];
    $current = (int) $user['xp_current_level'] + $xp;
    $total = (int) $user['xp_total'] + $xp;
    $skillPoints = (int) $user['skill_points'];
    $levelUps = 0;

    while ($current >= trailbound_level_xp_required($level)) {
        $current -= trailbound_level_xp_required($level);
        $level += 1;
        $skillPoints += 1;
        $levelUps += 1;
    }

    $db->prepare(
        'UPDATE users
         SET level = :level, xp_total = :xp_total, xp_current_level = :xp_current_level, skill_points = :skill_points
         WHERE id = :user_id'
    )->execute([
        'level' => $level,
        'xp_total' => $total,
        'xp_current_level' => $current,
        'skill_points' => $skillPoints,
        'user_id' => $userId,
    ]);

    return ['level_ups' => $levelUps, 'level' => $level];
}

function trailbound_add_notification(PDO $db, int $userId, string $type, string $title, string $message, array $data = []): void
{
    $db->prepare(
        'INSERT INTO notifications (user_id, type, title, message, data_json)
         VALUES (:user_id, :type, :title, :message, :data_json)'
    )->execute([
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'data_json' => $data ? json_encode($data, JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function trailbound_grant_chest(PDO $db, int $userId, string $chestCode, string $sourceType, ?int $sourceId = null): ?string
{
    $statement = $db->prepare('SELECT id, name FROM chests WHERE code = :code LIMIT 1');
    $statement->execute(['code' => $chestCode]);
    $chest = $statement->fetch();

    if (!$chest) {
        return null;
    }

    $db->prepare(
        'INSERT INTO user_chests (user_id, chest_id, source_type, source_id)
         VALUES (:user_id, :chest_id, :source_type, :source_id)'
    )->execute([
        'user_id' => $userId,
        'chest_id' => (int) $chest['id'],
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ]);

    $db->prepare(
        'INSERT INTO rewards_log (user_id, reward_type, reward_ref_id, description)
         VALUES (:user_id, "chest", :reward_ref_id, :description)'
    )->execute([
        'user_id' => $userId,
        'reward_ref_id' => (int) $chest['id'],
        'description' => $chest['name'],
    ]);

    return (string) $chest['name'];
}

function trailbound_update_stats(PDO $db, int $userId, array $activity): void
{
    $db->prepare(
        'UPDATE user_stats
         SET total_distance_m = total_distance_m + :distance_m,
             total_moving_time_s = total_moving_time_s + :moving_time_s,
             total_runs = total_runs + 1,
             current_week_runs = current_week_runs + 1,
             current_week_distance_m = current_week_distance_m + :distance_m,
             last_activity_at = :start_date
         WHERE user_id = :user_id'
    )->execute([
        'distance_m' => $activity['distance_m'],
        'moving_time_s' => $activity['moving_time_s'],
        'start_date' => $activity['start_date'],
        'user_id' => $userId,
    ]);
}

function trailbound_process_quests(PDO $db, int $userId, int $activityId, array $activity): array
{
    $completed = [];
    $rewardChests = [];
    $bonusXp = 0;

    $statsStatement = $db->prepare('SELECT total_distance_m, current_week_runs FROM user_stats WHERE user_id = :user_id LIMIT 1');
    $statsStatement->execute(['user_id' => $userId]);
    $stats = $statsStatement->fetch() ?: [];

    $quests = $db->prepare(
        'SELECT uq.id AS user_quest_id, uq.progress_value, q.id AS quest_id, q.name, q.objective_type,
                q.target_value, q.reward_xp, q.reward_chest_type
         FROM user_quests uq
         JOIN quests q ON q.id = uq.quest_id
         WHERE uq.user_id = :user_id AND uq.status = "active"'
    );
    $quests->execute(['user_id' => $userId]);

    foreach ($quests->fetchAll() as $quest) {
        $progress = (float) $quest['progress_value'];

        if ($quest['objective_type'] === 'single_run_distance_m') {
            $progress = max($progress, (float) $activity['distance_m']);
        } elseif ($quest['objective_type'] === 'weekly_run_count') {
            $progress = (float) ($stats['current_week_runs'] ?? 0);
        } elseif ($quest['objective_type'] === 'total_distance_m') {
            $progress = (float) ($stats['total_distance_m'] ?? 0);
        }

        $complete = $progress >= (float) $quest['target_value'];

        if ($complete) {
            $db->prepare(
                'UPDATE user_quests
                 SET progress_value = :progress_value,
                     status = "completed",
                     completed_at = NOW()
                 WHERE id = :user_quest_id'
            )->execute([
                'progress_value' => min($progress, (float) $quest['target_value']),
                'user_quest_id' => (int) $quest['user_quest_id'],
            ]);
        } else {
            $db->prepare(
                'UPDATE user_quests
                 SET progress_value = :progress_value
                 WHERE id = :user_quest_id'
            )->execute([
                'progress_value' => min($progress, (float) $quest['target_value']),
                'user_quest_id' => (int) $quest['user_quest_id'],
            ]);
        }

        if ($complete) {
            $completed[] = (string) $quest['name'];
            $bonusXp += (int) $quest['reward_xp'];

            if (!empty($quest['reward_chest_type'])) {
                $chestName = trailbound_grant_chest($db, $userId, (string) $quest['reward_chest_type'], 'quest', (int) $quest['quest_id']);

                if ($chestName) {
                    $rewardChests[] = $chestName;
                }
            }

            $db->prepare(
                'INSERT INTO rewards_log (user_id, activity_id, reward_type, amount, description)
                 VALUES (:user_id, :activity_id, "xp", :amount, :description)'
            )->execute([
                'user_id' => $userId,
                'activity_id' => $activityId,
                'amount' => (int) $quest['reward_xp'],
                'description' => 'Quest complete: ' . $quest['name'],
            ]);

            trailbound_add_notification($db, $userId, 'quest_complete', 'Quest complete: ' . $quest['name'], 'Rewards have been added to your character.');
        }
    }

    return [
        'completed' => $completed,
        'bonus_xp' => $bonusXp,
        'reward_chests' => $rewardChests,
    ];
}

function trailbound_update_area_progress(PDO $db, int $userId, int $activityId, array $activity): array
{
    $regions = $db->prepare(
        'SELECT ur.id AS user_region_id, ur.progress_m, r.id AS region_id, r.name, r.distance_required_m, r.sort_order
         FROM user_regions ur
         JOIN regions r ON r.id = ur.region_id
         WHERE ur.user_id = :user_id AND ur.is_unlocked = 1
         ORDER BY r.sort_order ASC'
    );
    $regions->execute(['user_id' => $userId]);
    $activeRegions = $regions->fetchAll();

    if (!$activeRegions) {
        return [];
    }

    $targetRegion = null;

    foreach ($activeRegions as $region) {
        if ((float) $region['progress_m'] < (float) $region['distance_required_m']) {
            $targetRegion = $region;
            break;
        }
    }

    $targetRegion ??= $activeRegions[count($activeRegions) - 1];
    $newProgress = (float) $targetRegion['progress_m'] + (float) $activity['distance_m'];
    $unlocked = [];

    $db->prepare('UPDATE user_regions SET progress_m = :progress_m WHERE id = :id')
        ->execute([
            'progress_m' => $newProgress,
            'id' => (int) $targetRegion['user_region_id'],
        ]);

    if ($newProgress >= (float) $targetRegion['distance_required_m']) {
        $next = $db->prepare(
            'SELECT id, name
             FROM regions
             WHERE sort_order > :sort_order AND is_active = 1
             ORDER BY sort_order ASC
             LIMIT 1'
        );
        $next->execute(['sort_order' => (int) $targetRegion['sort_order']]);
        $nextRegion = $next->fetch();

        if ($nextRegion) {
            $db->prepare(
                'INSERT INTO user_regions (user_id, region_id, progress_m, is_unlocked, unlocked_at)
                 VALUES (:user_id, :region_id, 0, 1, NOW())
                 ON DUPLICATE KEY UPDATE is_unlocked = 1, unlocked_at = COALESCE(unlocked_at, NOW())'
            )->execute([
                'user_id' => $userId,
                'region_id' => (int) $nextRegion['id'],
            ]);

            $db->prepare('UPDATE users SET current_region_id = :region_id WHERE id = :user_id')
                ->execute([
                    'region_id' => (int) $nextRegion['id'],
                    'user_id' => $userId,
                ]);

            $db->prepare('UPDATE user_stats SET areas_discovered = areas_discovered + 1 WHERE user_id = :user_id')
                ->execute(['user_id' => $userId]);

            $db->prepare(
                'INSERT INTO rewards_log (user_id, activity_id, reward_type, reward_ref_id, description)
                 VALUES (:user_id, :activity_id, "region_unlock", :reward_ref_id, :description)'
            )->execute([
                'user_id' => $userId,
                'activity_id' => $activityId,
                'reward_ref_id' => (int) $nextRegion['id'],
                'description' => $nextRegion['name'],
            ]);

            trailbound_add_notification($db, $userId, 'area_discovered', 'Area discovered: ' . $nextRegion['name'], 'The map has opened a new path.');
            $unlocked[] = (string) $nextRegion['name'];
        }
    }

    return $unlocked;
}

function trailbound_process_imported_activity(PDO $db, int $userId, int $activityId, array $activity): array
{
    $baseXp = trailbound_base_activity_xp($activity);
    trailbound_update_stats($db, $userId, $activity);
    $questResult = trailbound_process_quests($db, $userId, $activityId, $activity);
    $regionsUnlocked = trailbound_update_area_progress($db, $userId, $activityId, $activity);
    $totalXp = $baseXp + $questResult['bonus_xp'];
    $levelResult = trailbound_apply_xp($db, $userId, $totalXp);

    $db->prepare(
        'UPDATE activities SET xp_awarded = :xp_awarded, processed_at = NOW() WHERE id = :activity_id'
    )->execute([
        'xp_awarded' => $totalXp,
        'activity_id' => $activityId,
    ]);

    $db->prepare(
        'INSERT INTO rewards_log (user_id, activity_id, reward_type, amount, description)
         VALUES (:user_id, :activity_id, "xp", :amount, :description)'
    )->execute([
        'user_id' => $userId,
        'activity_id' => $activityId,
        'amount' => $baseXp,
        'description' => 'Run imported: ' . $activity['name'],
    ]);

    return [
        'id' => $activityId,
        'distance_km' => round($activity['distance_m'] / 1000, 2),
        'xp_awarded' => $totalXp,
        'level_ups' => $levelResult['level_ups'],
        'quests_completed' => $questResult['completed'],
        'rewards' => $questResult['reward_chests'],
        'regions_unlocked' => $regionsUnlocked,
    ];
}
