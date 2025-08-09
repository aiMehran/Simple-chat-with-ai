<?php

namespace CyrusUltimate\REST;

use CyrusUltimate\Services\JwtService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class AuthController
{
    const NAMESPACE = 'cyrus/v1';

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refresh'],
            'permission_callback' => '__return_true',
            'args' => [
                'refresh_token' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'jti' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/signup', [
            'methods' => 'POST',
            'callback' => [$this, 'signup'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function login(WP_REST_Request $request)
    {
        $creds = [
            'user_login' => $request->get_param('username'),
            'user_password' => $request->get_param('password'),
            'remember' => true,
        ];
        $user = wp_signon($creds);
        if ($user instanceof WP_Error) {
            return new WP_REST_Response(['message' => 'Invalid credentials'], 401);
        }

        $jwt = new JwtService();
        $scopes = array_values($user->roles);
        $access = $jwt->issueAccessToken($user->ID, $scopes);
        $refresh = $jwt->issueRefreshToken($user->ID);

        return new WP_REST_Response([
            'access_token' => $access['token'],
            'access_payload' => $access['payload'],
            'refresh_token' => $refresh['token'],
            'jti' => $refresh['jti'],
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles,
            ],
        ], 200);
    }

    public function refresh(WP_REST_Request $request)
    {
        $token = (string) $request->get_param('refresh_token');
        $jti = (string) $request->get_param('jti');
        $userId = (int) $request->get_param('user_id');

        $jwt = new JwtService();
        $rotated = $jwt->rotateRefreshToken($userId, $token, $jti);
        if ($rotated instanceof WP_Error) {
            return new WP_REST_Response(['message' => $rotated->get_error_message()], 401);
        }
        $access = $jwt->issueAccessToken($userId, array_values(get_userdata($userId)->roles));

        return new WP_REST_Response([
            'access_token' => $access['token'],
            'access_payload' => $access['payload'],
            'refresh_token' => $rotated['token'],
            'jti' => $rotated['jti'],
        ], 200);
    }

    public function signup(WP_REST_Request $request)
    {
        // Minimal stub; full activation code validation to be implemented in later phase
        $email = sanitize_email((string) $request->get_param('email'));
        $password = (string) $request->get_param('password');
        $first = sanitize_text_field((string) $request->get_param('first_name'));
        $last = sanitize_text_field((string) $request->get_param('last_name'));

        if (email_exists($email)) {
            return new WP_REST_Response(['message' => 'Email already registered'], 400);
        }

        $userId = wp_create_user($email, $password, $email);
        if (is_wp_error($userId)) {
            return new WP_REST_Response(['message' => $userId->get_error_message()], 400);
        }
        wp_update_user(['ID' => $userId, 'first_name' => $first, 'last_name' => $last, 'display_name' => trim($first . ' ' . $last)]);
        $user = get_userdata($userId);
        $user->set_role('cyrus_member');

        return new WP_REST_Response([
            'user' => [
                'id' => $userId,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'roles' => $user->roles,
            ],
        ], 201);
    }
}