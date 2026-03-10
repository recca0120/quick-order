<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\OrderOptions;
use Recca0120\QuickOrder\OrderService;
use Recca0120\QuickOrder\SerialNumber;
use WP_UnitTestCase;

class SerialNumberTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('quick_order_serial_salt_override');
        delete_option('quick_order_serial_salt');
        delete_option('quick_order_serial_enabled');
        parent::tearDown();
    }

    public function test_generate_returns_uppercase_hex_string()
    {
        $serial = SerialNumber::generate('QO-20260302-001', 'my-salt');

        $this->assertMatchesRegularExpression('/^[A-F0-9]{64}$/', $serial);
    }

    public function test_generate_is_deterministic()
    {
        $a = SerialNumber::generate('QO-20260302-001', 'salt');
        $b = SerialNumber::generate('QO-20260302-001', 'salt');

        $this->assertEquals($a, $b);
    }

    public function test_generate_differs_with_different_transaction_id()
    {
        $a = SerialNumber::generate('QO-20260302-001', 'salt');
        $b = SerialNumber::generate('QO-20260302-002', 'salt');

        $this->assertNotEquals($a, $b);
    }

    public function test_generate_differs_with_different_salt()
    {
        $a = SerialNumber::generate('QO-20260302-001', 'salt1');
        $b = SerialNumber::generate('QO-20260302-001', 'salt2');

        $this->assertNotEquals($a, $b);
    }

    public function test_order_service_stores_serial_when_salt_is_set()
    {
        update_option('quick_order_serial_salt', 'test-salt');

        $service = new OrderService();
        $order = $service->createOrder(100, new OrderOptions('', '', null, 'QO-TEST-001'));

        $orderNumber = $order->get_meta('_order_number');
        $expected = SerialNumber::generate($orderNumber, 'test-salt');

        $this->assertEquals($expected, $order->get_meta('_serial_number'));
    }

    public function test_order_service_does_not_store_serial_when_salt_is_empty()
    {
        delete_option('quick_order_serial_salt');

        $service = new OrderService();
        $order = $service->createOrder(100, new OrderOptions('', '', null, 'QO-TEST-002'));

        $this->assertEmpty($order->get_meta('_serial_number'));
    }

    public function test_serial_display_hook_registered_for_email()
    {
        $serialNumber = new SerialNumber();
        $serialNumber->register();

        $this->assertGreaterThan(0, has_action('woocommerce_email_order_meta', [$serialNumber, 'displayInEmail']));
    }

    public function test_serial_display_hook_registered_for_order_details()
    {
        $serialNumber = new SerialNumber();
        $serialNumber->register();

        $this->assertGreaterThan(0, has_action('woocommerce_order_details_after_order_table', [$serialNumber, 'displayInOrderDetails']));
    }

    public function test_serial_display_hook_registered_for_admin()
    {
        $serialNumber = new SerialNumber();
        $serialNumber->register();

        $this->assertGreaterThan(0, has_action('woocommerce_admin_order_data_after_billing_address', [$serialNumber, 'displayInAdmin']));
    }

    public function test_display_in_admin_outputs_serial_when_present()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'ADMIN123');
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInAdmin($order);
        $html = ob_get_clean();

        $this->assertStringContainsString('ADMIN123', $html);
    }

    public function test_display_in_admin_outputs_nothing_when_no_serial()
    {
        $order = wc_create_order();
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInAdmin($order);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    public function test_display_in_email_outputs_serial_when_completed()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'ABCDEF1234');
        $order->set_status('completed');
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInEmail($order, false, false, null);
        $html = ob_get_clean();

        $this->assertStringContainsString('ABCDEF1234', $html);
    }

    public function test_display_in_email_outputs_nothing_when_not_completed()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'ABCDEF1234');
        $order->set_status('pending');
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInEmail($order, false, false, null);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    public function test_display_in_email_outputs_nothing_when_no_serial()
    {
        $order = wc_create_order();
        $order->set_status('completed');
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInEmail($order, false, false, null);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    public function test_display_in_order_details_outputs_serial_when_completed()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'XYZ9876');
        $order->set_status('completed');
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInOrderDetails($order);
        $html = ob_get_clean();

        $this->assertStringContainsString('XYZ9876', $html);
    }

    public function test_display_in_order_details_outputs_nothing_when_not_completed()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'XYZ9876');
        $order->set_status('pending');
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInOrderDetails($order);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    public function test_display_in_order_details_outputs_nothing_when_no_serial()
    {
        $order = wc_create_order();
        $order->set_status('completed');
        $order->save();

        $serialNumber = new SerialNumber();

        ob_start();
        $serialNumber->displayInOrderDetails($order);
        $html = ob_get_clean();

        $this->assertEmpty($html);
    }

    // ── quick_order_serial_display filter ──────────────────────

    public function test_display_in_email_hidden_when_filter_returns_false()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'HIDE123');
        $order->set_status('completed');
        $order->save();

        add_filter('quick_order_serial_display', '__return_false');
        ob_start();
        (new SerialNumber())->displayInEmail($order, false, false, null);
        $html = ob_get_clean();
        remove_all_filters('quick_order_serial_display');

        $this->assertEmpty($html);
    }

    public function test_display_in_order_details_hidden_when_filter_returns_false()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'HIDE123');
        $order->set_status('completed');
        $order->save();

        add_filter('quick_order_serial_display', '__return_false');
        ob_start();
        (new SerialNumber())->displayInOrderDetails($order);
        $html = ob_get_clean();
        remove_all_filters('quick_order_serial_display');

        $this->assertEmpty($html);
    }

    public function test_display_in_admin_hidden_when_filter_returns_false()
    {
        $order = wc_create_order();
        $order->update_meta_data('_serial_number', 'HIDE123');
        $order->save();

        add_filter('quick_order_serial_display', '__return_false');
        ob_start();
        (new SerialNumber())->displayInAdmin($order);
        $html = ob_get_clean();
        remove_all_filters('quick_order_serial_display');

        $this->assertEmpty($html);
    }
}
