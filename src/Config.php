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
        return self::fromConstant('quick_order_api_key_override', 'QUICK_ORDER_API_KEY');
    }

    public static function serialSalt()
    {
        return self::serialSaltFromConstant() ?: get_option('quick_order_serial_salt', '');
    }

    public static function serialSaltFromConstant()
    {
        return self::fromConstant('quick_order_serial_salt_override', 'QUICK_ORDER_SERIAL_SALT');
    }

    private static function fromConstant(string $filter, string $constant)
    {
        $value = apply_filters($filter, null);
        if ($value) {
            return $value;
        }

        if (defined($constant)) {
            return constant($constant);
        }

        return null;
    }
}
