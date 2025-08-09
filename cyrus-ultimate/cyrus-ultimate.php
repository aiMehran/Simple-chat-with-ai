<?php
/**
 * Plugin Name: Cyrus Ultimate
 * Description: Modern project management platform (SPA) embedded in WordPress via shortcode, with custom REST API and JWT auth.
 * Version: 0.1.0
 * Author: Cyrus Team
 * License: GPLv2 or later
 * Text Domain: cyrus-ultimate
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('CYRUS_ULTIMATE_VERSION', '0.1.0');
define('CYRUS_ULTIMATE_PLUGIN_FILE', __FILE__);
define('CYRUS_ULTIMATE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CYRUS_ULTIMATE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Composer autoload
$composerAutoload = CYRUS_ULTIMATE_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

require_once CYRUS_ULTIMATE_PLUGIN_DIR . 'includes/Plugin.php';

register_activation_hook(__FILE__, function () {
    \CyrusUltimate\Plugin::activate();
});

register_deactivation_hook(__FILE__, function () {
    \CyrusUltimate\Plugin::deactivate();
});

add_action('plugins_loaded', function () {
    \CyrusUltimate\Plugin::instance()->boot();
});