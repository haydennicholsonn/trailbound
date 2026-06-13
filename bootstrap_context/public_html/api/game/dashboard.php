<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    $userId = trailbound_require_auth();
    $db = trailbound_db();

    $user = trailbound_fetch_user($db, $userId);

    if (!$user) {
        trailbound_api_error('USER_NOT_FOUND', 'User account was not found.', 404);
    }

    $stats = $db->prepare('SELECT * FROM user_stats WHERE user_id = :user_id LIMIT 1');
    $stats->execute(['user_id' => $userId]);

    $quests = $db->prepare(
        'SELECT uq.id AS user_quest_id, uq.status, uq.progress_value, q.name, q.description, q.quest_type,
                q.objective_type, q.target_value, q.reward_xp, q.reward_chest_type
         FROM user_quests uq
         JOIN quests q ON q.id = uq.quest_id
         WHERE uq.user_id = :user_id AND uq.status IN ("active", "completed")
         ORDER BY q.quest_type = "main" DESC, q.id ASC
         LIMIT 5'
    );
    $quests->execute(['user_id' => $userId]);

    $region = $db->prepare(
        'SELECT r.name, r.description, r.lore, r.distance_required_m, ur.progress_m, ur.is_unlocked
         FROM user_regions ur
         JOIN regions r ON r.id = ur.region_id
         WHERE ur.user_id = :user_id
         ORDER BY r.sort_order ASC
         LIMIT 1'
    );
    $region->execute(['user_id' => $userId]);

    $latest = $db->prepare(
        'SELECT id, name, distance_m, moving_time_s, average_pace_sec_per_km, start_date, xp_awarded
         FROM activities
         WHERE user_id = :user_id
         ORDER BY start_date DESC
         LIMIT 1'
    );
    $latest->execute(['user_id' => $userId]);

    $notifications = $db->prepare(
        'SELECT id, type, title, message, created_at
         FROM notifications
         WHERE user_id = :user_id AND read_at IS NULL
         ORDER BY created_at DESC
         LIMIT 5'
    );
    $notifications->execute(['user_id' => $userId]);

    trailbound_api_success([
        'user' => trailbound_user_payload($user),
        'stats' => $stats->fetch() ?: null,
        'active_quests' => $quests->fetchAll(),
        'latest_activity' => $latest->fetch() ?: null,
        'pending_rewards' => [],
        'current_region' => $region->fetch() ?: null,
        'notifications' => $notifications->fetchAll(),
    ]);
});
