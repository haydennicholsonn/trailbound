<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('POST');
    trailbound_require_csrf();
    $userId = trailbound_require_auth();

    $db = trailbound_db();
    $db->prepare(
        'UPDATE strava_connections
         SET is_active = 0, access_token = "", refresh_token = ""
         WHERE user_id = :user_id'
    )->execute(['user_id' => $userId]);

    trailbound_api_success([], 'Strava disconnected.');
});
