<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    $userId = trailbound_current_user_id();

    if (!$userId) {
        trailbound_api_success([
            'account' => false,
            'class_selected' => false,
            'strava_connected' => false,
            'starter_quest_assigned' => false,
        ]);
    }

    $db = trailbound_db();
    $user = trailbound_fetch_user($db, $userId);

    if (!$user) {
        trailbound_api_success([
            'account' => false,
            'class_selected' => false,
            'strava_connected' => false,
            'starter_quest_assigned' => false,
        ]);
    }

    $strava = $db->prepare('SELECT id FROM strava_connections WHERE user_id = :user_id AND is_active = 1 LIMIT 1');
    $strava->execute(['user_id' => $userId]);

    $starter = $db->prepare(
        "SELECT uq.id
         FROM user_quests uq
         JOIN quests q ON q.id = uq.quest_id
         WHERE uq.user_id = :user_id AND q.code = 'the_first_road'
         LIMIT 1"
    );
    $starter->execute(['user_id' => $userId]);

    trailbound_api_success([
        'account' => true,
        'class_selected' => !empty($user['avatar_class']),
        'strava_connected' => (bool) $strava->fetch(),
        'starter_quest_assigned' => (bool) $starter->fetch(),
    ]);
});
