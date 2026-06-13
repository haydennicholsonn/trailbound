<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('GET');
    trailbound_require_auth();

    $clientId = trailbound_env('STRAVA_CLIENT_ID');
    $redirectUri = trailbound_env('STRAVA_REDIRECT_URI');

    if (!$clientId || !$redirectUri || $clientId === 'change_me') {
        trailbound_api_error('STRAVA_NOT_CONFIGURED', 'Strava client details are not configured yet.', 503);
    }

    trailbound_start_session();
    $_SESSION['strava_oauth_state'] = bin2hex(random_bytes(24));

    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'approval_prompt' => 'auto',
        'scope' => 'read,activity:read_all',
        'state' => $_SESSION['strava_oauth_state'],
    ]);

    header('Location: https://www.strava.com/oauth/authorize?' . $params, true, 302);
    exit;
});
