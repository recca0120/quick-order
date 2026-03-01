<?php

require_once dirname(__DIR__).'/vendor/autoload.php';

require_once dirname(__DIR__).'/vendor/wp-phpunit/wp-phpunit/includes/functions.php';

function _manually_load_plugins()
{
    // Try sibling directory first (local dev), then WP plugins directory (CI)
    $locations = [
        dirname(dirname(__DIR__)).'/woocommerce/woocommerce.php',
        getenv('WP_CORE_DIR').'/wp-content/plugins/woocommerce/woocommerce.php',
    ];

    foreach ($locations as $path) {
        if ($path && file_exists($path)) {
            require $path;
            break;
        }
    }

    require dirname(__DIR__).'/quick-order.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugins');

require dirname(__DIR__).'/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';

// Ensure WooCommerce tables and roles exist (CI fresh install)
$administrator = get_role('administrator');
if (! $administrator || ! $administrator->has_cap('manage_woocommerce')) {
    WC_Install::create_tables();
    WC_Install::create_roles();

    // Reload roles from DB to pick up new caps
    global $wp_roles;
    $wp_roles = new WP_Roles;
}
