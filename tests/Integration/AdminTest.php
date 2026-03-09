<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\Admin;
use Recca0120\QuickOrder\OrderForm;
use Recca0120\QuickOrder\OrderService;
use WP_Ajax_UnitTestCase;

class AdminTest extends WP_Ajax_UnitTestCase
{
    /** @var Admin */
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminId = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($adminId);

        $this->admin = new Admin(new OrderService(), new OrderForm());
        $this->admin->register();
    }

    public function test_admin_menu_is_registered()
    {
        do_action('admin_menu');

        $url = menu_page_url('quick-order', false);
        $this->assertNotEmpty($url, 'Quick Order submenu should be registered under WooCommerce');
    }

    public function test_render_page_contains_order_form()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('qo-amount', $html);
        $this->assertStringContainsString('qo-description', $html);
        $this->assertStringContainsString('qo-note', $html);
        $this->assertStringContainsString('quick_order_nonce', $html);
    }

    public function test_ajax_create_order_success()
    {
        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '250';
        $_POST['description'] = '測試商品';
        $_POST['note'] = '';
        $_REQUEST['quick_order_nonce'] = $nonce;

        $response = $this->captureAjax(function () {
            $this->admin->ajaxCreateOrder();
        });

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('order_id', $response['data']);
        $this->assertArrayHasKey('payment_url', $response['data']);
        $this->assertEquals('250.00', $response['data']['total']);
    }

    public function test_ajax_create_order_rejects_without_permission()
    {
        $subscriberId = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriberId);

        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '100';
        $_REQUEST['quick_order_nonce'] = $nonce;

        $response = $this->captureAjax(function () {
            $this->admin->ajaxCreateOrder();
        });

        $this->assertFalse($response['success']);
    }

    public function test_settings_are_registered()
    {
        do_action('admin_init');

        $settings = get_registered_settings();
        $this->assertArrayHasKey('quick_order_api_key', $settings);
    }

    public function test_render_page_has_nav_tabs()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('nav-tab-wrapper', $html);
        $this->assertStringContainsString('tab-order', $html);
        $this->assertStringContainsString('tab-settings', $html);
    }

    public function test_render_page_has_card_wrapper()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('class="card"', $html);
    }

    public function test_render_page_contains_form_and_settings()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('quick_order_api_key', $html);
        $this->assertStringContainsString('qo-amount', $html);
    }

    public function test_api_key_field_shows_editable_input_when_no_constant()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderApiKeyField();
        $html = ob_get_clean();

        $this->assertStringContainsString('<input type="text"', $html);
        $this->assertStringNotContainsString('disabled', $html);
    }

    public function test_ajax_create_order_with_customer_data()
    {
        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '500';
        $_POST['description'] = '';
        $_POST['note'] = '';
        $_POST['name'] = 'Ajax Test';
        $_POST['email'] = 'ajax-customer@example.com';
        $_POST['phone_number'] = '0911111111';
        $_POST['address_1'] = '中正路100號';
        $_POST['city'] = '高雄市';
        $_POST['postcode'] = '800';

        $response = $this->captureAjax(function () {
            $this->admin->ajaxCreateOrder();
        });

        $this->assertTrue($response['success']);
        $order = wc_get_order($response['data']['order_id']);
        $this->assertEquals('ajax-customer@example.com', $order->get_billing_email());
        $this->assertEquals('Ajax', $order->get_billing_first_name());
        $this->assertEquals('0911111111', $order->get_billing_phone());
    }

    public function test_ajax_create_order_sanitizes_invalid_email()
    {
        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '100';
        $_POST['description'] = '';
        $_POST['note'] = '';
        $_POST['email'] = 'not-a-valid-email';

        $response = $this->captureAjax(function () {
            $this->admin->ajaxCreateOrder();
        });

        $this->assertTrue($response['success']);
        $order = wc_get_order($response['data']['order_id']);
        // sanitize_email strips invalid email, so billing email should be empty
        $this->assertEquals('', $order->get_billing_email());
    }

    public function test_auto_create_customer_setting_is_registered()
    {
        do_action('admin_init');

        $settings = get_registered_settings();
        $this->assertArrayHasKey('quick_order_auto_create_customer', $settings);
    }

    public function test_render_page_contains_auto_create_customer_setting()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('quick_order_auto_create_customer', $html);
    }

    public function test_auto_create_customer_setting_sanitizes_to_no_when_unchecked()
    {
        do_action('admin_init');

        $settings = get_registered_settings();
        $sanitize = $settings['quick_order_auto_create_customer']['sanitize_callback'];

        $this->assertEquals('yes', call_user_func($sanitize, 'yes'));
        $this->assertEquals('no', call_user_func($sanitize, ''));
        $this->assertEquals('no', call_user_func($sanitize, null));
        $this->assertEquals('no', call_user_func($sanitize, 'anything'));
    }

    public function test_ajax_create_order_returns_order_number()
    {
        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '100';
        $_POST['description'] = '';
        $_POST['note'] = '';

        $response = $this->captureAjax(function () {
            $this->admin->ajaxCreateOrder();
        });

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('order_number', $response['data']);
        $this->assertNotEmpty($response['data']['order_number']);
    }

    public function test_ajax_create_order_with_custom_order_number()
    {
        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '100';
        $_POST['description'] = '';
        $_POST['note'] = '';
        $_POST['order_number'] = 'CUSTOM-999';

        $response = $this->captureAjax(function () {
            $this->admin->ajaxCreateOrder();
        });

        $this->assertTrue($response['success']);
        $this->assertEquals('CUSTOM-999', $response['data']['order_number']);
    }

    public function test_custom_order_number_setting_is_registered()
    {
        do_action('admin_init');

        $settings = get_registered_settings();
        $this->assertArrayHasKey('quick_order_custom_order_number', $settings);
        $this->assertArrayHasKey('quick_order_order_prefix', $settings);
    }

    public function test_render_page_contains_order_number_settings()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('quick_order_custom_order_number', $html);
        $this->assertStringContainsString('quick_order_order_prefix', $html);
    }

    public function test_order_number_filter_returns_custom_number_when_enabled()
    {
        update_option('quick_order_custom_order_number', 'yes');

        $service = new OrderService();
        $order = $service->createOrder(100);
        $expectedNumber = $order->get_meta('_order_number');

        $this->admin->register();

        $result = apply_filters('woocommerce_order_number', $order->get_id(), $order);

        $this->assertEquals($expectedNumber, $result);
    }

    public function test_order_number_filter_returns_original_id_when_disabled()
    {
        // Remove the hook added by setUp's register()
        remove_all_filters('woocommerce_order_number');

        update_option('quick_order_custom_order_number', 'no');

        $admin = new Admin(new OrderService(), new OrderForm());
        $admin->register();

        $service = new OrderService();
        $order = $service->createOrder(100);

        $result = apply_filters('woocommerce_order_number', $order->get_id(), $order);

        $this->assertEquals($order->get_id(), $result);
    }

    public function test_order_number_filter_returns_original_id_when_no_meta()
    {
        update_option('quick_order_custom_order_number', 'yes');

        $this->admin->register();

        $order = wc_create_order();
        $order->save();

        $result = apply_filters('woocommerce_order_number', $order->get_id(), $order);

        $this->assertEquals($order->get_id(), $result);
    }

    // ── 補同步客戶關聯 ──

    public function test_render_page_contains_link_customer_tab()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('tab-tools', $html);
        $this->assertStringContainsString('qo-link-customer-form', $html);
    }

    public function test_ajax_link_customer_orders_success()
    {
        $email = 'linksync@example.com';

        $service = new OrderService();
        update_option('quick_order_auto_create_customer', 'no');
        $customer = \Recca0120\QuickOrder\Customer::fromArray(['email' => $email]);
        $service->createOrder(100, '', '', $customer);
        $service->createOrder(200, '', '', $customer);

        $this->factory->user->create(['user_email' => $email]);

        $nonce = wp_create_nonce('quick_order_link_customer');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['email'] = $email;

        $response = $this->captureAjax(function () {
            $this->admin->ajaxLinkCustomerOrders();
        });

        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['data']['linked']);
    }

    public function test_ajax_link_customer_orders_requires_permission()
    {
        $subscriberId = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriberId);

        $nonce = wp_create_nonce('quick_order_link_customer');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['email'] = 'test@example.com';

        $response = $this->captureAjax(function () {
            $this->admin->ajaxLinkCustomerOrders();
        });

        $this->assertFalse($response['success']);
    }

    public function test_ajax_link_customer_orders_returns_error_for_invalid_email()
    {
        $nonce = wp_create_nonce('quick_order_link_customer');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['email'] = 'not-an-email';

        $response = $this->captureAjax(function () {
            $this->admin->ajaxLinkCustomerOrders();
        });

        $this->assertFalse($response['success']);
    }

    // ── Serial Number Settings ──

    public function test_serial_settings_are_registered()
    {
        do_action('admin_init');

        $settings = get_registered_settings();
        $this->assertArrayHasKey('quick_order_serial_enabled', $settings);
        $this->assertArrayHasKey('quick_order_serial_salt', $settings);
    }

    public function test_render_page_contains_serial_settings()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('quick_order_serial_enabled', $html);
        $this->assertStringContainsString('quick_order_serial_salt', $html);
    }

    public function test_serial_salt_field_shows_editable_when_no_constant()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderSerialSaltField();
        $html = ob_get_clean();

        $this->assertStringContainsString('<input type="text"', $html);
        $this->assertStringNotContainsString('disabled', $html);
    }

    public function test_api_key_row_is_absent_from_settings_page_when_filter_set()
    {
        global $wp_settings_fields;
        $wp_settings_fields['quick-order-settings'] = [];

        add_filter('quick_order_api_key', function () {
            return 'from-filter';
        });

        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringNotContainsString('quick_order_api_key', $html);

        remove_all_filters('quick_order_api_key');
    }

    public function test_serial_salt_row_is_absent_from_settings_page_when_filter_set()
    {
        global $wp_settings_fields;
        $wp_settings_fields['quick-order-settings'] = [];

        add_filter('quick_order_serial_salt', function () {
            return 'from-filter';
        });

        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringNotContainsString('quick_order_serial_salt', $html);

        remove_all_filters('quick_order_serial_salt');
    }

    public function test_auto_create_customer_row_is_absent_from_settings_page_when_filter_set()
    {
        global $wp_settings_fields;
        $wp_settings_fields['quick-order-settings'] = [];

        add_filter('quick_order_auto_create_customer', function () {
            return 'yes';
        });

        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringNotContainsString('quick_order_auto_create_customer', $html);

        remove_all_filters('quick_order_auto_create_customer');
    }

    public function test_no_separate_settings_menu()
    {
        do_action('admin_menu');

        $url = menu_page_url('quick-order-settings', false);
        $this->assertEmpty($url, 'Should not have a separate settings submenu');
    }

    private function captureAjax(callable $callback): array
    {
        if (! defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        add_filter('wp_die_ajax_handler', function () {
            return function ($message) {
                throw new \WPDieException($message);
            };
        }, 99);

        ob_start();
        try {
            $callback();
        } catch (\WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();

        remove_all_filters('wp_die_ajax_handler');

        // wp_send_json may output multiple JSON objects; take the first one
        if (preg_match('/\{.*?"success":(true|false).*?\}/', $output, $matches)) {
            // Try to decode progressively longer substrings to get valid JSON
            for ($i = 1; $i <= strlen($output); $i++) {
                $response = json_decode(substr($output, 0, $i), true);
                if ($response !== null) {
                    return $response;
                }
            }
        }

        $response = json_decode($output, true);
        if ($response !== null) {
            return $response;
        }

        return ['success' => false, 'data' => ['message' => $output]];
    }
}
