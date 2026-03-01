<?php

namespace Suspended\QuickOrder\Tests\Integration;

use Suspended\QuickOrder\Admin;
use Suspended\QuickOrder\OrderForm;
use Suspended\QuickOrder\OrderService;
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

        $this->admin = new Admin(new OrderService, new OrderForm);
        $this->admin->register();
    }

    public function test_admin_menu_is_registered()
    {
        do_action('admin_menu');

        global $submenu;
        $found = false;
        if (isset($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $item) {
                if ($item[2] === 'quick-order') {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'Quick Order submenu should be registered under WooCommerce');
    }

    public function test_render_page_contains_order_form()
    {
        do_action('admin_init');

        ob_start();
        $this->admin->renderPage();
        $html = ob_get_clean();

        $this->assertStringContainsString('qo-amount', $html);
        $this->assertStringContainsString('qo-name', $html);
        $this->assertStringContainsString('qo-note', $html);
        $this->assertStringContainsString('quick_order_nonce', $html);
    }

    public function test_ajax_create_order_success()
    {
        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '250';
        $_POST['name'] = '測試商品';
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

        global $wp_registered_settings;
        $this->assertArrayHasKey('quick_order_api_key', $wp_registered_settings);
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

    public function test_api_key_field_shows_disabled_when_constant_defined()
    {
        do_action('admin_init');

        // Simulate constant by setting filter
        add_filter('quick_order_api_key_override', function () {
            return 'secret-from-config';
        });

        ob_start();
        $this->admin->renderApiKeyField();
        $html = ob_get_clean();

        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('*', $html);

        remove_all_filters('quick_order_api_key_override');
    }

    public function test_ajax_create_order_with_customer_data()
    {
        $nonce = wp_create_nonce('quick_order_create');
        $_POST['quick_order_nonce'] = $nonce;
        $_REQUEST['quick_order_nonce'] = $nonce;
        $_POST['amount'] = '500';
        $_POST['name'] = '';
        $_POST['note'] = '';
        $_POST['email'] = 'ajax-customer@example.com';
        $_POST['first_name'] = 'Ajax';
        $_POST['last_name'] = 'Test';
        $_POST['phone'] = '0911111111';
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
        $_POST['name'] = '';
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

        global $wp_registered_settings;
        $this->assertArrayHasKey('quick_order_auto_create_customer', $wp_registered_settings);
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

        global $wp_registered_settings;
        $sanitize = $wp_registered_settings['quick_order_auto_create_customer']['sanitize_callback'];

        $this->assertEquals('yes', call_user_func($sanitize, 'yes'));
        $this->assertEquals('no', call_user_func($sanitize, ''));
        $this->assertEquals('no', call_user_func($sanitize, null));
        $this->assertEquals('no', call_user_func($sanitize, 'anything'));
    }

    public function test_no_separate_settings_menu()
    {
        do_action('admin_menu');

        global $submenu;
        $found = false;
        if (isset($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $item) {
                if ($item[2] === 'quick-order-settings') {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertFalse($found, 'Should not have a separate settings submenu');
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
