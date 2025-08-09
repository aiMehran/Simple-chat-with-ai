<?php

namespace CyrusUltimate;

use CyrusUltimate\DB\Installer;
use CyrusUltimate\REST\AuthController;
use CyrusUltimate\REST\UsersController;

class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new Plugin();
        }
        return self::$instance;
    }

    public static function activate(): void
    {
        // Ensure JWT secret exists
        if (!get_option('cyrus_jwt_secret')) {
            $secret = wp_generate_password(64, true, true);
            add_option('cyrus_jwt_secret', $secret, '', false);
        }
        // Run DB installer
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        Installer::install();
        // Add custom roles/capabilities
        self::register_roles();
    }

    public static function deactivate(): void
    {
        // Intentionally left minimal for now
    }

    public function boot(): void
    {
        add_action('init', [self::class, 'register_roles']);

        add_action('rest_api_init', function () {
            (new AuthController())->register_routes();
            (new UsersController())->register_routes();
        });

        add_shortcode('cyrus_ultimate', [self::class, 'render_app_shortcode']);

        // Enqueue only when shortcode is present; the shortcode calls enqueue.
    }

    public static function register_roles(): void
    {
        // cyrus_admin
        add_role('cyrus_admin', __('Cyrus Admin', 'cyrus-ultimate'), [
            'read' => true,
            'upload_files' => true,
            'cyrus_manage' => true,
            'cyrus_invite' => true,
        ]);
        // cyrus_member
        add_role('cyrus_member', __('Cyrus Member', 'cyrus-ultimate'), [
            'read' => true,
            'upload_files' => true,
        ]);
        // Map custom caps to admin
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('cyrus_manage');
            $admin->add_cap('cyrus_invite');
        }
    }

    public static function render_app_shortcode($atts = []): string
    {
        self::enqueue_app_assets();
        return '<div id="cyrus-app" data-plugin-url="' . esc_attr(CYRUS_ULTIMATE_PLUGIN_URL) . '"></div>';
    }

    public static function enqueue_app_assets(): void
    {
        $manifestPath = CYRUS_ULTIMATE_PLUGIN_DIR . 'public/dist/manifest.json';
        $handle = 'cyrus-ultimate-app';

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            // Vite entry assumed as src/main.tsx
            $entry = $manifest['src/main.tsx'] ?? null;
            if ($entry) {
                $jsUrl = CYRUS_ULTIMATE_PLUGIN_URL . 'public/dist/' . $entry['file'];
                wp_register_script($handle, $jsUrl, [], CYRUS_ULTIMATE_VERSION, true);
                wp_enqueue_script($handle);
                if (!empty($entry['css'])) {
                    foreach ($entry['css'] as $cssFile) {
                        wp_enqueue_style($handle . '-' . md5($cssFile), CYRUS_ULTIMATE_PLUGIN_URL . 'public/dist/' . $cssFile, [], CYRUS_ULTIMATE_VERSION);
                    }
                }
            }
        } else {
            // Dev fallback: load from Vite dev server if available
            $devUrl = 'http://localhost:5173/src/main.tsx';
            wp_register_script($handle, $devUrl, [], time(), true);
            wp_enqueue_script($handle);
        }

        // Pass WP REST root and nonce for potential admin context
        wp_localize_script($handle, 'CyrusUltimateConfig', [
            'restBase' => esc_url_raw(get_rest_url(null, 'cyrus/v1')),
            'wpRestNonce' => wp_create_nonce('wp_rest'),
            'siteUrl' => site_url('/'),
        ]);
    }
}