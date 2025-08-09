<?php

namespace CyrusUltimate\DB;

use wpdb;

class Installer
{
    public static function install(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'cyrus_';

        $tables = [];

        $tables[] = "CREATE TABLE {$prefix}projects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            current_status VARCHAR(64) NOT NULL DEFAULT 'not_started',
            due_date DATETIME NULL,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX (owner_user_id),
            INDEX (current_status),
            INDEX (due_date)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}project_members (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(64) NOT NULL DEFAULT 'member',
            created_at DATETIME NOT NULL,
            UNIQUE KEY (project_id, user_id),
            INDEX (user_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}status_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(64) NOT NULL,
            occurred_at DATETIME NOT NULL,
            added_by_user_id BIGINT UNSIGNED NOT NULL,
            note TEXT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'manual',
            created_at DATETIME NOT NULL,
            INDEX (project_id, occurred_at),
            INDEX (status)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}media (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attachment_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'project',
            created_at DATETIME NOT NULL,
            UNIQUE KEY (attachment_id, project_id),
            INDEX (project_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}moodboards (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            auto_sync TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY (project_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}moodboard_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            moodboard_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            added_by_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (moodboard_id, attachment_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}likes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(32) NOT NULL,
            scope_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (scope, scope_id, user_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}comments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(32) NOT NULL,
            scope_id BIGINT UNSIGNED NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}whiteboards (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            created_by_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX (project_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}whiteboard_groups (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            whiteboard_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX (whiteboard_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}whiteboard_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (group_id, attachment_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}whiteboard_pins (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id BIGINT UNSIGNED NOT NULL,
            x FLOAT NOT NULL,
            y FLOAT NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL,
            text TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX (item_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}workflows (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY (project_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}workflow_stages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workflow_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            position INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX (workflow_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}tasks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            stage_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            due_date DATETIME NULL,
            status VARCHAR(64) NOT NULL DEFAULT 'todo',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX (project_id),
            INDEX (stage_id),
            INDEX (due_date)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}task_assignees (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY (task_id, user_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}activities (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NULL,
            actor_user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(64) NOT NULL,
            payload LONGTEXT NULL,
            occurred_at DATETIME NOT NULL,
            INDEX (project_id, occurred_at),
            INDEX (actor_user_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(64) NOT NULL,
            payload LONGTEXT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX (user_id, is_read)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}mentions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(32) NOT NULL,
            scope_id BIGINT UNSIGNED NOT NULL,
            mentioned_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX (mentioned_user_id)
        ) $charsetCollate;";

        $tables[] = "CREATE TABLE {$prefix}activation_codes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            invited_email VARCHAR(255) NULL,
            invited_by_user_id BIGINT UNSIGNED NOT NULL,
            expires_at DATETIME NULL,
            is_used TINYINT(1) NOT NULL DEFAULT 0,
            used_by_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (code_hash)
        ) $charsetCollate;";

        // Refresh tokens for JWT rotation
        $tables[] = "CREATE TABLE {$prefix}refresh_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            jti VARCHAR(128) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY (jti),
            INDEX (user_id),
            INDEX (expires_at)
        ) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }
}