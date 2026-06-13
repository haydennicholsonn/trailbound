<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    trailbound_start_session();

    $userId = trailbound_require_auth();
    $expectedState = $_SESSION['strava_oauth_state'] ?? '';
    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    $error = (string) ($_GET['error'] ?? '');

    if ($error !== '') {
        header('Location: ../../app/?strava=denied', true, 302);
        exit;
    }

    if ($code === '' || $state === '' || !is_string($expectedState) || !hash_equals($expectedState, $state)) {
        header('Location: ../../app/?strava=state_error', true, 302);
        exit;
    }

    unset($_SESSION['strava_oauth_state']);

    $token = trailbound_strava_exchange_code($code);
    $athleteId = (int) ($token['athlete']['id'] ?? 0);

    if ($athleteId <= 0) {
        header('Location: ../../app/?strava=athlete_error', true, 302);
        exit;
    }

    $db = trailbound_db();
    $existingAthlete = $db->prepare(
        'SELECT user_id
         FROM strava_connections
         WHERE strava_athlete_id = :strava_athlete_id AND user_id <> :user_id AND is_active = 1
         LIMIT 1'
    );
    $existingAthlete->execute([
        'strava_athlete_id' => $athleteId,
        'user_id' => $userId,
    ]);

    if ($existingAthlete->fetch()) {
        header('Location: ../../app/?strava=athlete_linked', true, 302);
        exit;
    }

    $db->prepare(
        'INSERT INTO strava_connections
            (user_id, strava_athlete_id, access_token, refresh_token, token_expires_at, scope, is_active)
         VALUES
            (:user_id, :strava_athlete_id, :access_token, :refresh_token, FROM_UNIXTIME(:expires_at), :scope, 1)
         ON DUPLICATE KEY UPDATE
            strava_athlete_id = VALUES(strava_athlete_id),
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            token_expires_at = VALUES(token_expires_at),
            scope = VALUES(scope),
            is_active = 1'
    )->execute([
        'user_id' => $userId,
        'strava_athlete_id' => $athleteId,
        'access_token' => trailbound_encrypt_secret((string) ($token['access_token'] ?? '')),
        'refresh_token' => trailbound_encrypt_secret((string) ($token['refresh_token'] ?? '')),
        'expires_at' => (int) ($token['expires_at'] ?? time()),
        'scope' => (string) ($_GET['scope'] ?? ''),
    ]);

    header('Location: ../../app/?strava=connected', true, 302);
    exit;
});
