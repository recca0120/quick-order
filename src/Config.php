<?php

namespace Recca0120\QuickOrder;

class Config
{
    public static function apiKey()
    {
        return self::apiKeyFromConstant() ?: get_option('quick_order_api_key');
    }

    public static function apiKeyFromConstant()
    {
        $value = apply_filters('quick_order_api_key_override', null);
        if ($value) {
            return $value;
        }

        if (defined('QUICK_ORDER_API_KEY')) {
            return QUICK_ORDER_API_KEY;
        }

        return null;
    }

    public static function serialSalt()
    {
        return self::serialSaltFromConstant() ?: get_option('quick_order_serial_salt', '');
    }

    public static function serialSaltFromConstant()
    {
        $value = apply_filters('quick_order_serial_salt_override', null);
        if ($value) {
            return $value;
        }

        if (defined('QUICK_ORDER_SERIAL_SALT')) {
            return QUICK_ORDER_SERIAL_SALT;
        }

        return null;
    }
}
