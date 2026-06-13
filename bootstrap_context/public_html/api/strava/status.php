<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    $userId = trailbound_current_user_id();

    if (!$userId) {
        trailbound_api_success([
            'connected' => false,
            'athlete_id' => null,
            'last_sync_at' => null,
        ]);
    }

    $db = trailbound_db();
    $statement = $db->prepare(
        'SELECT strava_athlete_id, last_sync_at
         FROM strava_connections
         WHERE user_id = :user_id AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $connection = $statement->fetch();

    trailbound_api_success([
        'connected' => (bool) $connection,
        'athlete_id' => $connection ? (int) $connection['strava_athlete_id'] : null,
        'last_sync_at' => $connection['last_sync_at'] ?? null,
    ]);
});
