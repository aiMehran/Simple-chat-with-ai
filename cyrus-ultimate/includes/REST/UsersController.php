<?php

namespace CyrusUltimate\REST;

use CyrusUltimate\Services\JwtService;
use WP_REST_Request;
use WP_REST_Response;

class UsersController
{
    const NAMESPACE = 'cyrus/v1';

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/users/me', [
            'methods' => 'GET',
            'callback' => [$this, 'me'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/users/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => false,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    private function requireBearerUserId(WP_REST_Request $request): ?int
    {
        $auth = $request->get_header('authorization');
        if (!$auth || stripos($auth, 'bearer ') !== 0) {
            return null;
        }
        $token = trim(substr($auth, 7));
        $jwt = new JwtService();
        $decoded = $jwt->validateAccessToken($token);
        if (is_wp_error($decoded)) {
            return null;
        }
        return (int) ($decoded->sub ?? 0);
    }

    public function me(WP_REST_Request $request)
    {
        $userId = $this->requireBearerUserId($request);
        if (!$userId) {
            return new WP_REST_Response(['message' => 'Unauthorized'], 401);
        }
        $user = get_userdata($userId);
        if (!$user) {
            return new WP_REST_Response(['message' => 'User not found'], 404);
        }
        return new WP_REST_Response([
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => $user->roles,
        ], 200);
    }

    public function search(WP_REST_Request $request)
    {
        $userId = $this->requireBearerUserId($request);
        if (!$userId) {
            return new WP_REST_Response(['message' => 'Unauthorized'], 401);
        }
        $q = sanitize_text_field((string) $request->get_param('q'));
        $args = [
            'search' => '*' . esc_attr($q) . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 10,
        ];
        $users = get_users($args);
        $data = array_map(function ($u) {
            return [
                'id' => $u->ID,
                'name' => $u->display_name,
                'email' => $u->user_email,
            ];
        }, $users);
        return new WP_REST_Response($data, 200);
    }
}