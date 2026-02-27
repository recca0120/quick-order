<?php

namespace Suspended\QuickOrder;

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
}
