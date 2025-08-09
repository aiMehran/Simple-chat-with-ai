<?php

namespace CyrusUltimate\Admin;

class SettingsPage
{
    public static function register(): void
    {
        add_action('admin_menu', function () {
            add_menu_page(
                __('Cyrus Ultimate', 'cyrus-ultimate'),
                __('Cyrus Ultimate', 'cyrus-ultimate'),
                'manage_options',
                'cyrus-ultimate',
                [self::class, 'render_page'],
                'dashicons-art',
                56
            );
        });

        add_action('admin_init', function () {
            register_setting('cyrus_theme_settings', 'cyrus_theme_settings');
            add_settings_section('cyrus_theme_section', __('Theme', 'cyrus-ultimate'), '__return_null', 'cyrus-ultimate');
            add_settings_field('primary', __('Primary Color (hex)', 'cyrus-ultimate'), [self::class, 'field_text'], 'cyrus-ultimate', 'cyrus_theme_section', ['key' => 'primary']);
            add_settings_field('defaultTheme', __('Default Theme', 'cyrus-ultimate'), [self::class, 'field_select_theme'], 'cyrus-ultimate', 'cyrus_theme_section', ['key' => 'defaultTheme']);
        });
    }

    public static function render_page(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Cyrus Ultimate Settings', 'cyrus-ultimate') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('cyrus_theme_settings');
        do_settings_sections('cyrus-ultimate');
        submit_button();
        echo '</form></div>';
    }

    public static function field_text(array $args): void
    {
        $opts = get_option('cyrus_theme_settings', []);
        $key = $args['key'];
        $value = esc_attr($opts[$key] ?? '');
        echo '<input type="text" name="cyrus_theme_settings[' . esc_attr($key) . ']" value="' . $value . '" class="regular-text" />';
    }

    public static function field_select_theme(array $args): void
    {
        $opts = get_option('cyrus_theme_settings', []);
        $key = $args['key'];
        $value = esc_attr($opts[$key] ?? 'light');
        echo '<select name="cyrus_theme_settings[' . esc_attr($key) . ']">';
        echo '<option value="light"' . selected($value, 'light', false) . '>Light</option>';
        echo '<option value="dark"' . selected($value, 'dark', false) . '>Dark</option>';
        echo '</select>';
    }
}