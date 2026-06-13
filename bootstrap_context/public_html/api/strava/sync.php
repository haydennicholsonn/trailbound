<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

trailbound_endpoint(function (): void {
    trailbound_require_method('POST');
    trailbound_require_csrf();
    $userId = trailbound_require_auth();
    $db = trailbound_db();

    $connectionStatement = $db->prepare(
        'SELECT *
         FROM strava_connections
         WHERE user_id = :user_id AND is_active = 1
         LIMIT 1'
    );
    $connectionStatement->execute(['user_id' => $userId]);
    $connection = $connectionStatement->fetch();

    if (!$connection) {
        trailbound_api_error('STRAVA_NOT_CONNECTED', 'Connect Strava before syncing runs.', 409);
    }

    $accessToken = trailbound_decrypt_secret((string) $connection['access_token']);
    $refreshToken = trailbound_decrypt_secret((string) $connection['refresh_token']);
    $expiresAt = strtotime((string) $connection['token_expires_at']);

    if ($expiresAt === false || $expiresAt <= time() + 60) {
        $token = trailbound_strava_refresh_token($refreshToken);
        $accessToken = (string) ($token['access_token'] ?? '');
        $refreshToken = (string) ($token['refresh_token'] ?? $refreshToken);

        $db->prepare(
            'UPDATE strava_connections
             SET access_token = :access_token,
                 refresh_token = :refresh_token,
                 token_expires_at = FROM_UNIXTIME(:expires_at)
             WHERE id = :id'
        )->execute([
            'access_token' => trailbound_encrypt_secret($accessToken),
            'refresh_token' => trailbound_encrypt_secret($refreshToken),
            'expires_at' => (int) ($token['expires_at'] ?? time() + 3600),
            'id' => (int) $connection['id'],
        ]);
    }

    $activities = trailbound_strava_fetch_activities($accessToken);
    $imported = [];
    $ignored = 0;

    foreach ($activities as $activity) {
        if (!is_array($activity) || !trailbound_strava_is_run($activity)) {
            $ignored += 1;
            continue;
        }

        $normalized = trailbound_normalize_strava_activity($activity);

        if ($normalized['provider_activity_id'] === '') {
            $ignored += 1;
            continue;
        }

        $duplicate = $db->prepare(
            'SELECT id FROM activities WHERE provider = "strava" AND provider_activity_id = :provider_activity_id LIMIT 1'
        );
        $duplicate->execute(['provider_activity_id' => $normalized['provider_activity_id']]);

        if ($duplicate->fetch()) {
            continue;
        }

        [$validationStatus, $validationReason] = trailbound_validate_activity($normalized);

        try {
            $db->beginTransaction();

            $insert = $db->prepare(
                'INSERT INTO activities
                    (user_id, provider, provider_activity_id, activity_type, name, distance_m, moving_time_s,
                     elapsed_time_s, average_speed_mps, average_pace_sec_per_km, start_date, timezone,
                     summary_polyline, start_lat, start_lng, end_lat, end_lng, raw_json,
                     validation_status, validation_reason, processed_at)
                 VALUES
                    (:user_id, "strava", :provider_activity_id, :activity_type, :name, :distance_m, :moving_time_s,
                     :elapsed_time_s, :average_speed_mps, :average_pace_sec_per_km, :start_date, :timezone,
                     :summary_polyline, :start_lat, :start_lng, :end_lat, :end_lng, :raw_json,
                     :validation_status, :validation_reason, :processed_at)'
            );
            $insert->execute([
                'user_id' => $userId,
                'provider_activity_id' => $normalized['provider_activity_id'],
                'activity_type' => $normalized['activity_type'],
                'name' => $normalized['name'],
                'distance_m' => $normalized['distance_m'],
                'moving_time_s' => $normalized['moving_time_s'],
                'elapsed_time_s' => $normalized['elapsed_time_s'],
                'average_speed_mps' => $normalized['average_speed_mps'],
                'average_pace_sec_per_km' => $normalized['average_pace_sec_per_km'],
                'start_date' => $normalized['start_date'],
                'timezone' => $normalized['timezone'],
                'summary_polyline' => $normalized['summary_polyline'],
                'start_lat' => $normalized['start_lat'],
                'start_lng' => $normalized['start_lng'],
                'end_lat' => $normalized['end_lat'],
                'end_lng' => $normalized['end_lng'],
                'raw_json' => $normalized['raw_json'],
                'validation_status' => $validationStatus,
                'validation_reason' => $validationReason,
                'processed_at' => $validationStatus === 'valid' ? null : date('Y-m-d H:i:s'),
            ]);

            $activityId = (int) $db->lastInsertId();

            if ($validationStatus === 'valid') {
                $imported[] = trailbound_process_imported_activity($db, $userId, $activityId, $normalized);
            } else {
                $ignored += 1;
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    $db->prepare('UPDATE strava_connections SET last_sync_at = NOW() WHERE id = :id')
        ->execute(['id' => (int) $connection['id']]);

    trailbound_api_success([
        'imported_count' => count($imported),
        'ignored_count' => $ignored,
        'activities' => $imported,
    ], count($imported) ? 'Runs synced.' : 'No new valid runs found.');
});
