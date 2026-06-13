<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Jwt
{
    public static function issue(User $user): string
    {
        $now = time();
        $payload = [
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'exp' => $now + (60 * 60 * 24 * 30),
            'typ' => 'access',
        ];

        return self::encode(['alg' => 'HS256', 'typ' => 'JWT']) . '.' . self::encode($payload) . '.' . self::sign($payload);
    }

    public static function userFromRequest(Request $request): ?User
    {
        $token = $request->bearerToken() ?: $request->cookie('trailbound_token');
        if (! $token || substr_count($token, '.') !== 2) {
            return null;
        }

        [$header, $body, $signature] = explode('.', $token, 3);
        $payload = json_decode(self::decode($body), true);
        if (! is_array($payload) || empty($payload['sub']) || empty($payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        if (! hash_equals(self::sign($payload), $signature)) {
            return null;
        }

        return User::query()->with(['profile', 'regionProgress.region', 'taskStates.task.region'])->find($payload['sub']);
    }

    private static function sign(array $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', self::encode(['alg' => 'HS256', 'typ' => 'JWT']) . '.' . self::encode($payload), self::secret(), true)), '+/', '-_'), '=');
    }

    private static function encode(array $value): string
    {
        return rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }

    private static function secret(): string
    {
        return Str::after((string) config('app.key'), 'base64:') ?: (string) config('app.key');
    }
}
