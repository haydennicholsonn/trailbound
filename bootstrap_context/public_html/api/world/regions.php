<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    $userId = trailbound_require_auth();
    $db = trailbound_db();

    $statement = $db->prepare(
        'SELECT r.id, r.code, r.name, r.description, r.lore, r.distance_required_m, r.sort_order,
                COALESCE(ur.progress_m, 0) AS progress_m,
                COALESCE(ur.is_unlocked, 0) AS is_unlocked,
                ur.unlocked_at
         FROM regions r
         LEFT JOIN user_regions ur ON ur.region_id = r.id AND ur.user_id = :user_id
         WHERE r.is_active = 1
         ORDER BY r.sort_order ASC'
    );
    $statement->execute(['user_id' => $userId]);

    trailbound_api_success([
        'regions' => $statement->fetchAll(),
    ]);
});
