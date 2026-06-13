<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('POST');
    trailbound_start_session();

    $data = trailbound_json_body();
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        trailbound_api_error('INVALID_CREDENTIALS', 'Email or password is incorrect.', 401);
    }

    $db = trailbound_db();
    $statement = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        trailbound_api_error('INVALID_CREDENTIALS', 'Email or password is incorrect.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    trailbound_api_success([
        'user' => trailbound_user_payload($user),
        'csrf_token' => trailbound_csrf_token(),
    ], 'Welcome back, adventurer.');
});
