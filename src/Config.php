<?php

namespace Recca0120\QuickOrder;

class Config
{
    public static function apiKey(): string
    {
        return (string) apply_filters('quick_order_api_key', get_option('quick_order_api_key', ''));
    }

    public static function serialSalt(): string
    {
        return (string) apply_filters('quick_order_serial_salt', get_option('quick_order_serial_salt', ''));
    }

    public static function autoCreateCustomer(): string
    {
        return (string) apply_filters('quick_order_auto_create_customer', get_option('quick_order_auto_create_customer', 'no'));
    }

    public static function isOverridden(string $filter): bool
    {
        return apply_filters($filter, null) !== null;
    }
}
