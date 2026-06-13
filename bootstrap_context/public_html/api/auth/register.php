<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('POST');
    trailbound_start_session();

    $data = trailbound_json_body();
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $displayName = trim((string) ($data['display_name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        trailbound_api_error('VALIDATION_ERROR', 'Enter a valid email address.', 422);
    }

    if (strlen($password) < 10) {
        trailbound_api_error('VALIDATION_ERROR', 'Password must be at least 10 characters.', 422);
    }

    if ($displayName === '' || mb_strlen($displayName) > 80) {
        trailbound_api_error('VALIDATION_ERROR', 'Display name is required and must be under 80 characters.', 422);
    }

    $db = trailbound_db();
    $existing = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $existing->execute(['email' => $email]);

    if ($existing->fetch()) {
        trailbound_api_error('EMAIL_EXISTS', 'That email already has a Trailbound account.', 409);
    }

    $db->beginTransaction();

    $insert = $db->prepare(
        'INSERT INTO users (email, password_hash, display_name)
         VALUES (:email, :password_hash, :display_name)'
    );
    $insert->execute([
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => $displayName,
    ]);

    $userId = (int) $db->lastInsertId();
    trailbound_seed_new_user($db, $userId);
    $db->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    $user = trailbound_fetch_user($db, $userId);

    trailbound_api_success([
        'user' => trailbound_user_payload($user),
        'csrf_token' => trailbound_csrf_token(),
    ], 'Account created. The First Road is waiting.');
});
