<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    $userId = trailbound_require_auth();
    $db = trailbound_db();

    $statement = $db->prepare(
        'SELECT uq.id AS user_quest_id, uq.status, uq.progress_value, uq.completed_at,
                q.name, q.description, q.quest_type, q.objective_type, q.target_value,
                q.reward_xp, q.reward_coins, q.reward_chest_type
         FROM user_quests uq
         JOIN quests q ON q.id = uq.quest_id
         WHERE uq.user_id = :user_id AND uq.status IN ("active", "completed")
         ORDER BY q.quest_type = "main" DESC, q.created_at ASC'
    );
    $statement->execute(['user_id' => $userId]);

    trailbound_api_success([
        'quests' => $statement->fetchAll(),
    ]);
});
