<?php

define('DB_NAME', getenv('DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = getenv('WP_TABLE_PREFIX') ?: 'wptests_';

define('WP_PHP_BINARY', 'php');
define('WP_TESTS_DOMAIN', getenv('WP_TESTS_DOMAIN') ?: 'example.org');
define('WP_TESTS_EMAIL', getenv('WP_TESTS_EMAIL') ?: 'admin@example.org');
define('WP_TESTS_TITLE', getenv('WP_TESTS_TITLE') ?: 'Test Blog');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

if (! defined('ABSPATH')) {
    $wp_core_dir = getenv('WP_CORE_DIR');

    if (! $wp_core_dir) {
        $plugin_dir = dirname(__DIR__);
        $local_wp = $plugin_dir . '/.wordpress-test/wordpress';
        if (is_dir($local_wp)) {
            $wp_core_dir = $local_wp;
        } else {
            $wp_core_dir = dirname($plugin_dir, 3);
        }
    }

    define('ABSPATH', rtrim($wp_core_dir, '/') . '/');
}

if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

define('DB_ENGINE', getenv('DB_ENGINE') ?: 'mysql');

if (DB_ENGINE === 'sqlite') {
    $db_file = getenv('DB_FILE') ?: 'test.sqlite';
    $db_dir = getenv('DB_DIR') ?: '/tmp/wp-phpunit-tests/';

    if (! is_dir($db_dir)) {
        @mkdir($db_dir, 0755, true);
    }

    define('DB_FILE', $db_file);
    define('DB_DIR', $db_dir);

    $db_dropin_source = WP_CONTENT_DIR . '/plugins/sqlite-database-integration/wp-includes/sqlite/db.php';
    $db_dropin_target = WP_CONTENT_DIR . '/db.php';

    if (! file_exists($db_dropin_target)) {
        @symlink($db_dropin_source, $db_dropin_target);
    }
}
