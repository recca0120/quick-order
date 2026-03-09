<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\Config;
use WP_UnitTestCase;

class ConfigTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('quick_order_serial_salt_override');
        delete_option('quick_order_serial_salt');
        parent::tearDown();
    }

    public function test_serial_salt_returns_option_when_no_constant()
    {
        update_option('quick_order_serial_salt', 'my-salt');

        $this->assertEquals('my-salt', Config::serialSalt());
    }

    public function test_serial_salt_returns_filter_value_over_option()
    {
        update_option('quick_order_serial_salt', 'from-option');

        add_filter('quick_order_serial_salt_override', function () {
            return 'from-filter';
        });

        $this->assertEquals('from-filter', Config::serialSalt());
    }

    public function test_serial_salt_from_constant_returns_null_when_not_set()
    {
        $this->assertNull(Config::serialSaltFromConstant());
    }

    public function test_serial_salt_from_constant_returns_filter_value()
    {
        add_filter('quick_order_serial_salt_override', function () {
            return 'filter-salt';
        });

        $this->assertEquals('filter-salt', Config::serialSaltFromConstant());
    }
}
