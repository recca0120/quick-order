<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

require_once dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit/includes/functions.php';

function _manually_load_plugins()
{
    require dirname(dirname(__DIR__)) . '/woocommerce/woocommerce.php';
    require dirname(__DIR__) . '/quick-order.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugins');

require dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';
