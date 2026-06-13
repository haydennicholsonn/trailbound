<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    trailbound_start_session();

    $userId = trailbound_current_user_id();

    if (!$userId) {
        trailbound_api_success([
            'authenticated' => false,
            'csrf_token' => trailbound_csrf_token(),
        ]);
    }

    $db = trailbound_db();
    $user = trailbound_fetch_user($db, $userId);

    if (!$user) {
        unset($_SESSION['user_id']);
        trailbound_api_success([
            'authenticated' => false,
            'csrf_token' => trailbound_csrf_token(),
        ]);
    }

    trailbound_api_success([
        'authenticated' => true,
        'csrf_token' => trailbound_csrf_token(),
        'user' => trailbound_user_payload($user),
    ]);
});
