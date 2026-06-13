<?php

namespace App\Support;

use App\Models\StravaConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StravaService
{
    private const AUTH_URL = 'https://www.strava.com/oauth/authorize';
    private const TOKEN_URL = 'https://www.strava.com/oauth/token';
    private const API_BASE = 'https://www.strava.com/api/v3';

    public static function authorizationUrl(): string
    {
        $clientId = config('services.strava.client_id');
        $redirectUri = config('services.strava.redirect_uri');
        $verifyToken = config('services.strava.verify_token');

        if (!$clientId || !$redirectUri) {
            throw new RuntimeException('Strava client ID and redirect URI must be configured.');
        }

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'approval_prompt' => 'auto',
            'scope' => 'read,activity:read_all',
            'state' => $verifyToken ?: 'trailbound',
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    /**
     * @throws RuntimeException
     */
    public static function exchangeCode(string $code): array
    {
        return self::tokenRequest([
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * @throws RuntimeException
     */
    public static function refreshAccessToken(string $refreshToken): array
    {
        return self::tokenRequest([
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    /**
     * @throws RuntimeException
     */
    public static function fetchActivities(StravaConnection $connection, int $page = 1, int $perPage = 30): array
    {
        return self::apiGet($connection, '/athlete/activities', ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * @throws RuntimeException
     */
    public static function fetchActivity(StravaConnection $connection, int $activityId): array
    {
        return self::apiGet($connection, '/activities/' . $activityId);
    }

    public static function storeConnection(int $userId, array $tokenData): StravaConnection
    {
        return StravaConnection::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'strava_athlete_id' => $tokenData['athlete']['id'] ?? 0,
                'access_token' => encrypt($tokenData['access_token']),
                'refresh_token' => encrypt($tokenData['refresh_token']),
                'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 21600),
                'scope' => $tokenData['scope'] ?? null,
                'is_active' => true,
            ]
        );
    }

    public static function getAccessToken(StravaConnection $connection): string
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $refreshToken = decrypt($connection->refresh_token);
            $tokenData = self::refreshAccessToken($refreshToken);

            $connection->update([
                'access_token' => encrypt($tokenData['access_token']),
                'refresh_token' => encrypt($tokenData['refresh_token']),
                'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 21600),
            ]);

            return $tokenData['access_token'];
        }

        return decrypt($connection->access_token);
    }

    public static function verifyWebhookChallenge(string $mode, string $token, string $challenge): string
    {
        $verifyToken = config('services.strava.verify_token');

        if ($mode !== 'subscribe' || $token !== $verifyToken) {
            throw new RuntimeException('Invalid webhook verification.');
        }

        return $challenge;
    }

    public static function isRun(array $activity): bool
    {
        $type = strtolower((string) ($activity['type'] ?? ''));
        $sportType = strtolower((string) ($activity['sport_type'] ?? ''));

        return in_array($type, ['run', 'virtualrun', 'trailrun'], true)
            || in_array($sportType, ['run', 'virtualrun', 'trailrun'], true);
    }

    public static function normalizeActivity(array $raw): array
    {
        $distanceM = (float) ($raw['distance'] ?? 0);
        $movingTime = (int) ($raw['moving_time'] ?? 0);
        $distanceKm = $distanceM / 1000;

        return [
            'external_id' => (string) ($raw['id'] ?? ''),
            'distance_km' => round($distanceKm, 2),
            'duration_minutes' => round($movingTime / 60, 1),
            'run_at' => ($raw['start_date'] ?? now()),
            'polyline' => $raw['map']['summary_polyline'] ?? null,
            'start_lat' => $raw['start_latlng'][0] ?? null,
            'start_lng' => $raw['start_latlng'][1] ?? null,
        ];
    }

    /**
     * @throws RuntimeException
     */
    private static function tokenRequest(array $params): array
    {
        $clientId = config('services.strava.client_id');
        $clientSecret = config('services.strava.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new RuntimeException('Strava credentials are not configured.');
        }

        try {
            $response = Http::asForm()->post(self::TOKEN_URL, array_merge([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ], $params));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Strava: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            $message = $response->json('message') ?? 'Strava token exchange failed.';
            throw new RuntimeException($message);
        }

        return $response->json();
    }

    /**
     * @throws RuntimeException
     */
    private static function apiGet(StravaConnection $connection, string $path, array $params = []): array
    {
        $accessToken = self::getAccessToken($connection);

        try {
            $response = Http::withToken($accessToken)
                ->withQueryParameters($params)
                ->get(self::API_BASE . $path);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Strava: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            $message = $response->json('message') ?? 'Strava API request failed.';
            throw new RuntimeException($message);
        }

        return $response->json();
    }
}
