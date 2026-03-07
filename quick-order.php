<?php

/**
 * Plugin Name: WooCommerce Quick Order
 * Description: 快速建立指定金額的 WooCommerce 訂單。
 * Version: 1.0.0
 * Author: Recca0120
 * Text Domain: quick-order
 * Requires Plugins: woocommerce
 */
if (! defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'Suspended\\QuickOrder\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__.'/src/'.str_replace('\\', '/', $relativeClass).'.php';

    if (file_exists($file)) {
        require $file;
    }
});

add_action('plugins_loaded', function () {
    if (! class_exists('WooCommerce')) {
        return;
    }

    $orderService = new Suspended\QuickOrder\OrderService;
    $orderForm = new Suspended\QuickOrder\OrderForm;

    $admin = new Suspended\QuickOrder\Admin($orderService, $orderForm);
    $admin->register();

    $shortcode = new Suspended\QuickOrder\Shortcode($orderForm);
    $shortcode->register();

    $restApi = new Suspended\QuickOrder\RestApi($orderService);
    $restApi->register();
});
