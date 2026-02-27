<?php

namespace Suspended\QuickOrder\Tests\Integration;

use Suspended\QuickOrder\Admin;
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

        $this->admin = new Admin(new OrderService());
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

    public function test_render_form_contains_required_fields()
    {
        ob_start();
        $this->admin->renderForm();
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
