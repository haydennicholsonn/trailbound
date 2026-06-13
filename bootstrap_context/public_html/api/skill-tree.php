<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    $userId = trailbound_require_auth();
    $db = trailbound_db();

    $nodes = $db->query(
        'SELECT id, code, name, branch, description, effect_type, effect_value, cost, position_x, position_y
         FROM skill_nodes
         WHERE is_active = 1
         ORDER BY FIELD(branch, "endurance", "explorer", "tempo"), id ASC'
    )->fetchAll();

    $unlocked = $db->prepare(
        'SELECT node_id, unlocked_at
         FROM user_skill_nodes
         WHERE user_id = :user_id'
    );
    $unlocked->execute(['user_id' => $userId]);

    $prerequisites = $db->query(
        'SELECT node_id, prerequisite_node_id
         FROM skill_node_prerequisites'
    )->fetchAll();

    $user = trailbound_fetch_user($db, $userId);

    trailbound_api_success([
        'available_skill_points' => (int) ($user['skill_points'] ?? 0),
        'nodes' => $nodes,
        'unlocked_nodes' => $unlocked->fetchAll(),
        'prerequisites' => $prerequisites,
    ]);
});
