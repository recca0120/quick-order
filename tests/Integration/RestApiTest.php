<?php

namespace Suspended\QuickOrder\Tests\Integration;

use Suspended\QuickOrder\OrderService;
use Suspended\QuickOrder\RestApi;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

class RestApiTest extends WP_UnitTestCase
{
    /** @var OrderService */
    private $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server;

        $this->orderService = new OrderService;
        $restApi = new RestApi($this->orderService);
        $restApi->register();

        do_action('rest_api_init');
    }

    protected function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;

        delete_option('quick_order_api_key');
        parent::tearDown();
    }

    // ── POST /orders ──

    public function test_create_order_endpoint_returns_201()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 150);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('payment_url', $data);
        $this->assertEquals('150.00', $data['total']);
        $this->assertEquals('pending', $data['status']);
    }

    public function test_create_order_endpoint_requires_permission()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status());
    }

    public function test_create_order_endpoint_requires_amount()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_create_order_with_custom_name_and_note()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 500);
        $request->set_param('name', '會員儲值');
        $request->set_param('note', '測試備註');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('500.00', $data['total']);
    }

    // ── API Key 認證 ──

    public function test_api_key_grants_access_without_login()
    {
        $apiKey = 'test-api-key-12345';
        update_option('quick_order_api_key', $apiKey);
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', $apiKey);
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
    }

    public function test_invalid_api_key_is_rejected()
    {
        $apiKey = 'test-api-key-12345';
        update_option('quick_order_api_key', $apiKey);
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', 'wrong-key');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status());
    }

    public function test_no_api_key_configured_rejects_guest()
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', 'any-key');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status());
    }

    public function test_api_key_from_constant_grants_access()
    {
        add_filter('quick_order_api_key_override', function () {
            return 'constant-key-999';
        });
        delete_option('quick_order_api_key');
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', 'constant-key-999');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());

        remove_all_filters('quick_order_api_key_override');
    }

    public function test_constant_api_key_takes_precedence_over_option()
    {
        update_option('quick_order_api_key', 'option-key');
        add_filter('quick_order_api_key_override', function () {
            return 'constant-key';
        });
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', 'option-key');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status(), 'Option key should not work when constant is set');

        remove_all_filters('quick_order_api_key_override');
    }

    // ── GET /orders/{id} ──

    public function test_get_order_returns_order_details()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $order = $this->orderService->createOrder(200, '測試商品');

        $request = new WP_REST_Request('GET', '/quick-order/v1/orders/'.$order->get_id());
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals($order->get_id(), $data['order_id']);
        $this->assertEquals('200.00', $data['total']);
        $this->assertEquals('pending', $data['status']);
        $this->assertArrayHasKey('payment_url', $data);
    }

    public function test_get_order_returns_404_for_invalid_id()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('GET', '/quick-order/v1/orders/999999');
        $response = rest_do_request($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ── PUT /orders/{id}/status ──

    public function test_update_order_status()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $order = $this->orderService->createOrder(300);

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/'.$order->get_id().'/status');
        $request->set_param('status', 'processing');
        $request->set_param('note', '已收到款項');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('processing', $data['status']);
    }

    public function test_update_order_status_requires_status_param()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $order = $this->orderService->createOrder(100);

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/'.$order->get_id().'/status');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_update_order_status_returns_404_for_invalid_id()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/999999/status');
        $request->set_param('status', 'completed');

        $response = rest_do_request($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ── GET /orders ──

    public function test_list_orders()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $this->orderService->createOrder(100, '訂單一');
        $this->orderService->createOrder(200, '訂單二');

        $request = new WP_REST_Request('GET', '/quick-order/v1/orders');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_list_orders_with_status_filter()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $order1 = $this->orderService->createOrder(100);
        $order2 = $this->orderService->createOrder(200);
        $this->orderService->updateOrderStatus($order1->get_id(), 'processing');

        $request = new WP_REST_Request('GET', '/quick-order/v1/orders');
        $request->set_param('status', 'processing');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        foreach ($data as $item) {
            $this->assertEquals('processing', $item['status']);
        }
    }

    // ── API Key + 新端點 ──

    public function test_api_key_works_for_get_order()
    {
        $apiKey = 'test-key-get';
        update_option('quick_order_api_key', $apiKey);
        wp_set_current_user(0);

        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $order = $this->orderService->createOrder(100);
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/quick-order/v1/orders/'.$order->get_id());
        $request->set_header('X-API-Key', $apiKey);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function test_api_key_works_for_update_status()
    {
        $apiKey = 'test-key-put';
        update_option('quick_order_api_key', $apiKey);
        wp_set_current_user(0);

        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $order = $this->orderService->createOrder(100);
        wp_set_current_user(0);

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/'.$order->get_id().'/status');
        $request->set_header('X-API-Key', $apiKey);
        $request->set_param('status', 'completed');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }
}
