<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\Config;
use WP_UnitTestCase;

class ConfigTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('quick_order_api_key');
        remove_all_filters('quick_order_serial_salt');
        remove_all_filters('quick_order_auto_create_customer');
        delete_option('quick_order_api_key');
        delete_option('quick_order_serial_salt');
        delete_option('quick_order_auto_create_customer');
        parent::tearDown();
    }

    public function test_api_key_returns_option_value()
    {
        update_option('quick_order_api_key', 'option-key');

        $this->assertEquals('option-key', Config::apiKey());
    }

    public function test_api_key_returns_filter_value_over_option()
    {
        update_option('quick_order_api_key', 'from-option');

        add_filter('quick_order_api_key', function () {
            return 'from-filter';
        });

        $this->assertEquals('from-filter', Config::apiKey());
    }

    public function test_api_key_returns_empty_string_when_not_set()
    {
        $this->assertEquals('', Config::apiKey());
    }

    public function test_serial_salt_returns_option_value()
    {
        update_option('quick_order_serial_salt', 'my-salt');

        $this->assertEquals('my-salt', Config::serialSalt());
    }

    public function test_serial_salt_returns_filter_value_over_option()
    {
        update_option('quick_order_serial_salt', 'from-option');

        add_filter('quick_order_serial_salt', function () {
            return 'from-filter';
        });

        $this->assertEquals('from-filter', Config::serialSalt());
    }

    public function test_serial_salt_returns_empty_string_when_not_set()
    {
        $this->assertEquals('', Config::serialSalt());
    }

    public function test_auto_create_customer_returns_option_value()
    {
        update_option('quick_order_auto_create_customer', 'yes');

        $this->assertEquals('yes', Config::autoCreateCustomer());
    }

    public function test_auto_create_customer_returns_filter_value_over_option()
    {
        update_option('quick_order_auto_create_customer', 'no');

        add_filter('quick_order_auto_create_customer', function () {
            return 'yes';
        });

        $this->assertEquals('yes', Config::autoCreateCustomer());
    }

    public function test_auto_create_customer_returns_no_when_not_set()
    {
        $this->assertEquals('no', Config::autoCreateCustomer());
    }

    public function test_is_overridden_returns_false_when_no_filter()
    {
        $this->assertFalse(Config::isOverridden('quick_order_api_key'));
    }

    public function test_is_overridden_returns_true_when_filter_set()
    {
        add_filter('quick_order_api_key', function () {
            return 'from-filter';
        });

        $this->assertTrue(Config::isOverridden('quick_order_api_key'));
    }
}
