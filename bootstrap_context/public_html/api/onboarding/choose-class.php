<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('POST');
    trailbound_require_csrf();
    $userId = trailbound_require_auth();

    $data = trailbound_json_body();
    $class = strtolower(trim((string) ($data['avatar_class'] ?? '')));

    if (!in_array($class, trailbound_allowed_classes(), true)) {
        trailbound_api_error('VALIDATION_ERROR', 'Choose a valid class.', 422);
    }

    $db = trailbound_db();
    $db->prepare('UPDATE users SET avatar_class = :avatar_class WHERE id = :user_id')
        ->execute([
            'avatar_class' => $class,
            'user_id' => $userId,
        ]);

    $user = trailbound_fetch_user($db, $userId);

    trailbound_api_success([
        'user' => trailbound_user_payload($user),
    ], 'Class selected.');
});
