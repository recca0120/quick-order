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
    $prefix = 'Recca0120\\QuickOrder\\';
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

    $orderService = new Recca0120\QuickOrder\OrderService();
    $orderForm = new Recca0120\QuickOrder\OrderForm();

    $admin = new Recca0120\QuickOrder\Admin($orderService, $orderForm);
    $admin->register();

    $shortcode = new Recca0120\QuickOrder\Shortcode($orderForm);
    $shortcode->register();

    $restApi = new Recca0120\QuickOrder\RestApi(new Recca0120\QuickOrder\OrderSyncer($orderService));
    $restApi->register();

    $serialNumber = new Recca0120\QuickOrder\SerialNumber();
    $serialNumber->register();
});
