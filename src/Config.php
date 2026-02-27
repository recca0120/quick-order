<?php

namespace Suspended\QuickOrder;

class Config
{
    public static function apiKey()
    {
        $value = apply_filters('quick_order_api_key_from_constant', null);
        if ($value) {
            return $value;
        }

        if (defined('QUICK_ORDER_API_KEY')) {
            return QUICK_ORDER_API_KEY;
        }

        return get_option('quick_order_api_key');
    }

    public static function apiKeyFromConstant()
    {
        $value = apply_filters('quick_order_api_key_from_constant', null);
        if ($value) {
            return $value;
        }

        if (defined('QUICK_ORDER_API_KEY')) {
            return QUICK_ORDER_API_KEY;
        }

        return null;
    }
}
