<?php

namespace Suspended\QuickOrder\Tests\Integration;

use Suspended\QuickOrder\OrderForm;
use Suspended\QuickOrder\Shortcode;
use WP_UnitTestCase;

class ShortcodeTest extends WP_UnitTestCase
{
    /** @var Shortcode */
    private $shortcode;

    protected function setUp(): void
    {
        parent::setUp();
        $orderForm = new OrderForm;
        $this->shortcode = new Shortcode($orderForm);
        $this->shortcode->register();
    }

    public function test_shortcode_renders_form_for_admin()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $output = do_shortcode('[quick_order]');

        $this->assertStringContainsString('qo-amount', $output);
        $this->assertStringContainsString('quick-order-frontend', $output);
    }

    public function test_shortcode_returns_empty_for_subscriber()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        $output = do_shortcode('[quick_order]');

        $this->assertEmpty($output);
    }

    public function test_shortcode_returns_empty_for_guest()
    {
        wp_set_current_user(0);

        $output = do_shortcode('[quick_order]');

        $this->assertEmpty($output);
    }
}
