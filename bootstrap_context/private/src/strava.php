<?php

declare(strict_types=1);

function trailbound_strava_request(string $method, string $url, array $params = [], ?string $accessToken = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for Strava requests.');
    }

    $ch = curl_init();
    $headers = ['Accept: application/json'];

    if ($accessToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    if ($method === 'GET' && $params) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);

    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Strava request failed: ' . $error);
    }

    $data = json_decode($raw, true);

    if ($status < 200 || $status >= 300) {
        $message = is_array($data) ? ($data['message'] ?? 'Strava request failed.') : 'Strava request failed.';
        throw new RuntimeException($message);
    }

    return is_array($data) ? $data : [];
}

function trailbound_strava_exchange_code(string $code): array
{
    return trailbound_strava_request('POST', 'https://www.strava.com/oauth/token', [
        'client_id' => trailbound_env('STRAVA_CLIENT_ID'),
        'client_secret' => trailbound_env('STRAVA_CLIENT_SECRET'),
        'code' => $code,
        'grant_type' => 'authorization_code',
    ]);
}

function trailbound_strava_refresh_token(string $refreshToken): array
{
    return trailbound_strava_request('POST', 'https://www.strava.com/oauth/token', [
        'client_id' => trailbound_env('STRAVA_CLIENT_ID'),
        'client_secret' => trailbound_env('STRAVA_CLIENT_SECRET'),
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
}

function trailbound_strava_fetch_activities(string $accessToken): array
{
    return trailbound_strava_request('GET', 'https://www.strava.com/api/v3/athlete/activities', [
        'page' => 1,
        'per_page' => 30,
    ], $accessToken);
}

function trailbound_strava_is_run(array $activity): bool
{
    $type = strtolower((string) ($activity['type'] ?? ''));
    $sportType = strtolower((string) ($activity['sport_type'] ?? ''));

    return in_array($type, ['run', 'virtualrun', 'trailrun'], true)
        || in_array($sportType, ['run', 'virtualrun', 'trailrun'], true);
}
