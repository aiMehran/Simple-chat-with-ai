<?php

namespace CyrusUltimate\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use WP_Error;

class JwtService
{
    private string $secret;
    private string $issuer;

    public function __construct()
    {
        $this->secret = (string) get_option('cyrus_jwt_secret', '');
        $this->issuer = site_url('/');
    }

    public function issueAccessToken(int $userId, array $scopes = [], int $ttlSeconds = 900): array
    {
        $now = time();
        $payload = [
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
            'scope' => $scopes,
            'jti' => wp_generate_password(16, false, false),
        ];
        $token = JWT::encode($payload, $this->secret, 'HS256');
        return ['token' => $token, 'payload' => $payload];
    }

    public function validateAccessToken(string $token)
    {
        try {
            return JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable $e) {
            return new WP_Error('invalid_token', $e->getMessage());
        }
    }

    public function issueRefreshToken(int $userId, int $ttlSeconds = 1209600): array
    {
        $now = time();
        $jti = wp_generate_password(32, false, false);
        $rawToken = wp_generate_password(64, true, true);
        $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);

        global $wpdb;
        $table = $wpdb->prefix . 'cyrus_refresh_tokens';
        $wpdb->insert($table, [
            'user_id' => $userId,
            'jti' => $jti,
            'token_hash' => $tokenHash,
            'expires_at' => gmdate('Y-m-d H:i:s', $now + $ttlSeconds),
            'revoked' => 0,
            'created_at' => gmdate('Y-m-d H:i:s', $now),
        ]);

        return ['token' => $rawToken, 'jti' => $jti, 'expires_at' => $now + $ttlSeconds];
    }

    public function rotateRefreshToken(int $userId, string $presentedToken, string $jti)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cyrus_refresh_tokens';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE jti = %s AND user_id = %d", $jti, $userId));
        if (!$row) {
            return new \WP_Error('invalid_refresh', 'Refresh token not found');
        }
        if ((int) $row->revoked === 1) {
            return new \WP_Error('invalid_refresh', 'Refresh token revoked');
        }
        if (strtotime((string) $row->expires_at) < time()) {
            return new \WP_Error('invalid_refresh', 'Refresh token expired');
        }
        if (!password_verify($presentedToken, (string) $row->token_hash)) {
            return new \WP_Error('invalid_refresh', 'Refresh token mismatch');
        }
        // Revoke old
        $wpdb->update($table, ['revoked' => 1], ['id' => (int) $row->id]);
        // Issue new
        return $this->issueRefreshToken($userId);
    }
}