<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

trailbound_load_env(trailbound_base_path('.env'));

$appEnv = trailbound_env('APP_ENV', 'local');

if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

require_once __DIR__ . '/database.php';
require_once trailbound_base_path('src/strava.php');
require_once trailbound_base_path('src/game.php');

function trailbound_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function trailbound_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('trailbound_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => trailbound_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function trailbound_json_body(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        trailbound_api_error('INVALID_JSON', 'Request body must be valid JSON.', 400);
    }

    return $data;
}

function trailbound_api_success(array $data = [], string $message = ''): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => $message,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function trailbound_api_error(string $code, string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function trailbound_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        trailbound_api_error('METHOD_NOT_ALLOWED', 'Method not allowed.', 405);
    }
}

function trailbound_csrf_token(): string
{
    trailbound_start_session();
    return (string) $_SESSION['csrf_token'];
}

function trailbound_require_csrf(): void
{
    trailbound_start_session();

    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token = $_SESSION['csrf_token'] ?? '';

    if (!is_string($header) || !is_string($token) || !hash_equals($token, $header)) {
        trailbound_api_error('CSRF_MISMATCH', 'Security token mismatch. Refresh and try again.', 419);
    }
}

function trailbound_current_user_id(): ?int
{
    trailbound_start_session();
    $userId = $_SESSION['user_id'] ?? null;

    return is_numeric($userId) ? (int) $userId : null;
}

function trailbound_require_auth(): int
{
    $userId = trailbound_current_user_id();

    if (!$userId) {
        trailbound_api_error('AUTH_REQUIRED', 'You need to log in first.', 401);
    }

    return $userId;
}

function trailbound_user_payload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'email' => $user['email'] ?? null,
        'display_name' => $user['display_name'],
        'avatar_class' => $user['avatar_class'],
        'level' => (int) $user['level'],
        'xp_total' => (int) $user['xp_total'],
        'xp_current_level' => (int) $user['xp_current_level'],
        'skill_points' => (int) $user['skill_points'],
        'coins' => (int) $user['coins'],
    ];
}

function trailbound_allowed_classes(): array
{
    return ['wayfarer', 'scout', 'warden', 'strider', 'stormrunner'];
}

function trailbound_crypto_key(): string
{
    return hash('sha256', trailbound_env('APP_KEY', 'local-dev-key'), true);
}

function trailbound_encrypt_secret(string $plain): string
{
    if ($plain === '' || !function_exists('openssl_encrypt')) {
        return $plain;
    }

    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', trailbound_crypto_key(), OPENSSL_RAW_DATA, $iv);

    if ($cipher === false) {
        return $plain;
    }

    return base64_encode($iv . $cipher);
}

function trailbound_decrypt_secret(string $encoded): string
{
    if ($encoded === '' || !function_exists('openssl_decrypt')) {
        return $encoded;
    }

    $payload = base64_decode($encoded, true);

    if ($payload === false || strlen($payload) <= 16) {
        return $encoded;
    }

    $iv = substr($payload, 0, 16);
    $cipher = substr($payload, 16);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', trailbound_crypto_key(), OPENSSL_RAW_DATA, $iv);

    return $plain === false ? $encoded : $plain;
}

function trailbound_endpoint(callable $handler): void
{
    try {
        $handler();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $message = trailbound_env('APP_ENV', 'local') === 'production'
            ? 'Database request failed.'
            : $exception->getMessage();

        trailbound_api_error('DATABASE_ERROR', $message, 503);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $message = trailbound_env('APP_ENV', 'local') === 'production'
            ? 'Server request failed.'
            : $exception->getMessage();

        trailbound_api_error('SERVER_ERROR', $message, 500);
    }
}

function trailbound_fetch_user(PDO $db, int $userId): ?array
{
    $statement = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function trailbound_seed_new_user(PDO $db, int $userId): void
{
    $db->prepare('INSERT INTO user_stats (user_id) VALUES (:user_id)')
        ->execute(['user_id' => $userId]);

    $region = $db->query("SELECT id FROM regions WHERE code = 'first_road' LIMIT 1")->fetch();

    if ($region) {
        $db->prepare(
            'INSERT INTO user_regions (user_id, region_id, progress_m, is_unlocked, unlocked_at)
             VALUES (:user_id, :region_id, 0, 1, NOW())'
        )->execute([
            'user_id' => $userId,
            'region_id' => (int) $region['id'],
        ]);

        $db->prepare('UPDATE users SET current_region_id = :region_id WHERE id = :user_id')
            ->execute([
                'region_id' => (int) $region['id'],
                'user_id' => $userId,
            ]);
    }

    $quest = $db->query("SELECT id FROM quests WHERE code = 'the_first_road' LIMIT 1")->fetch();

    if ($quest) {
        $db->prepare('INSERT INTO user_quests (user_id, quest_id) VALUES (:user_id, :quest_id)')
            ->execute([
                'user_id' => $userId,
                'quest_id' => (int) $quest['id'],
            ]);
    }
}
