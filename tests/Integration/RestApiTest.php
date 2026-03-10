<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\OrderService;
use Recca0120\QuickOrder\RestApi;
use WP_REST_Request;
use WP_UnitTestCase;

class RestApiTest extends WP_UnitTestCase
{
    /** @var OrderService */
    private $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = new OrderService();
        $orderSyncer = new \Recca0120\QuickOrder\OrderSyncer($this->orderService);
        $restApi = new RestApi($orderSyncer);
        $restApi->register();

        do_action('rest_api_init');
    }

    protected function tearDown(): void
    {
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
        $request->set_param('description', '會員儲值');
        $request->set_param('note', '測試備註');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('500.00', $data['total']);
    }

    public function test_create_order_with_customer_data()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 300);
        $request->set_param('name', 'Rest Client');
        $request->set_param('email', 'rest-customer@example.com');
        $request->set_param('phone_number', '0922222222');
        $request->set_param('address_1', '忠孝東路');
        $request->set_param('city', '台北市');
        $request->set_param('postcode', '100');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $order = wc_get_order($data['order_id']);
        $this->assertEquals('rest-customer@example.com', $order->get_billing_email());
        $this->assertEquals('Rest', $order->get_billing_first_name());
        $this->assertEquals('0922222222', $order->get_billing_phone());
    }

    public function test_create_order_returns_auto_generated_order_number()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('order_number', $data);
        $this->assertNotEmpty($data['order_number']);
    }

    public function test_create_order_with_custom_order_number()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 100);
        $request->set_param('order_number', 'EXT-12345');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('EXT-12345', $data['order_number']);
    }

    // ── 金流欄位 ──

    public function test_create_order_with_transaction_reference()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 1000);
        $request->set_param('transaction_reference', 'GW-REF-999');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $order = wc_get_order($response->get_data()['order_id']);
        $this->assertEquals('GW-REF-999', $order->get_transaction_id());
    }

    public function test_create_order_with_payment_method()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 1000);
        $request->set_param('gateway_name', 'newebpay');
        $request->set_param('payment_method', 'atm');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $order = wc_get_order($response->get_data()['order_id']);
        $this->assertEquals('omnipay_newebpay_atm', $order->get_payment_method());
        $this->assertEquals('newebpay', $order->get_payment_method_title());
    }

    public function test_create_order_with_dates()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 1000);
        $request->set_param('created_at', '2026-03-07T20:28:15.000000Z');
        $request->set_param('completed_at', '2026-03-07T20:30:00.000000Z');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $order = wc_get_order($response->get_data()['order_id']);
        $this->assertEquals('2026-03-07 20:28:15', $order->get_date_created()->date('Y-m-d H:i:s'));
        $this->assertEquals('2026-03-07 20:30:00', $order->get_date_paid()->date('Y-m-d H:i:s'));
    }

    public function test_create_order_with_extra_payment_fields()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 1000);
        $request->set_param('bank_code', '001');
        $request->set_param('account_number', '12345678901');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $order = wc_get_order($response->get_data()['order_id']);
        $this->assertEquals('001', $order->get_meta('_payment_bank_code'));
        $this->assertEquals('12345678901', $order->get_meta('_payment_account_number'));
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

    public function test_api_key_from_filter_grants_access()
    {
        add_filter('quick_order_api_key', function () {
            return 'filter-key-999';
        });
        delete_option('quick_order_api_key');
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', 'filter-key-999');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());

        remove_all_filters('quick_order_api_key');
    }

    public function test_filter_api_key_takes_precedence_over_option()
    {
        update_option('quick_order_api_key', 'option-key');
        add_filter('quick_order_api_key', function () {
            return 'filter-key';
        });
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', 'option-key');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status(), 'Option key should not work when filter overrides');

        remove_all_filters('quick_order_api_key');
    }

    // ── POST /orders/sync ──

    public function test_sync_endpoint_creates_order_with_full_payment_data()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders/sync');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'transaction_id' => 'QO-20260309-099',
            'amount' => 1000,
            'description' => '同步商品',
            'status' => 'completed',
            'name' => '王小明',
            'email' => 'wang@example.com',
            'gateway_name' => 'newebpay',
            'payment_method' => 'atm',
            'bank_code' => '001',
            'account_number' => '12345678901',
        ]));

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $order = wc_get_order($data['order_id']);
        $this->assertEquals('QO-20260309-099', $data['order_number']);
        $this->assertEquals('completed', $order->get_status());
        $this->assertEquals('omnipay_newebpay_atm', $order->get_payment_method());
        $this->assertEquals('001', $order->get_meta('_payment_bank_code'));
    }

    public function test_sync_endpoint_updates_existing_order()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $order = $this->orderService->createOrder(1000, '商品', '', null, 'QO-20260309-100');

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders/sync');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'transaction_id' => 'QO-20260309-100',
            'amount' => 1000,
            'status' => 'completed',
            'transaction_reference' => 'GW-REF-999',
        ]));

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals($order->get_id(), $data['order_id']);
        $updated = wc_get_order($data['order_id']);
        $this->assertEquals('completed', $updated->get_status());
        $this->assertEquals('GW-REF-999', $updated->get_transaction_id());
    }

    // ── PUT /orders/{transaction_id}/status ──

    public function test_update_status_by_transaction_id()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $this->orderService->createOrder(300, '', '', null, 'QO-TX-001');

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/QO-TX-001/status');
        $request->set_param('status', 'processing');
        $request->set_param('note', '已處理');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('processing', $response->get_data()['status']);
    }

    public function test_update_status_returns_404_for_unknown_transaction_id()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/UNKNOWN-TX/status');
        $request->set_param('status', 'completed');

        $response = rest_do_request($request);

        $this->assertEquals(404, $response->get_status());
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

        $this->orderService->createOrder(300, '', '', null, 'QO-STATUS-001');

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/QO-STATUS-001/status');
        $request->set_param('status', 'processing');
        $request->set_param('note', '已收到款項');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('processing', $response->get_data()['status']);
    }

    public function test_update_order_status_requires_status_param()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $this->orderService->createOrder(100, '', '', null, 'QO-STATUS-002');

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/QO-STATUS-002/status');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_update_order_status_returns_404_for_invalid_id()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/UNKNOWN-999/status');
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

    // ── POST /customers/link-orders ──

    public function test_link_customer_orders_links_guest_orders_to_user()
    {
        $email = 'linkcustomer@example.com';
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        // Create guest orders before the user account exists
        update_option('quick_order_auto_create_customer', 'no');
        $customer = \Recca0120\QuickOrder\Customer::fromArray(['email' => $email]);
        $this->orderService->createOrder(100, '', '', $customer);
        $this->orderService->createOrder(200, '', '', $customer);

        // User registers later
        self::factory()->user->create(['user_email' => $email]);

        $request = new WP_REST_Request('POST', '/quick-order/v1/customers/link-orders');
        $request->set_param('email', $email);
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(2, $response->get_data()['linked']);
    }

    public function test_link_customer_orders_returns_zero_for_unknown_email()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/customers/link-orders');
        $request->set_param('email', 'nobody@example.com');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(0, $response->get_data()['linked']);
    }

    public function test_link_customer_orders_requires_permission()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/customers/link-orders');
        $request->set_param('email', 'someone@example.com');
        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status());
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

        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $this->orderService->createOrder(100, '', '', null, 'QO-APIKEY-001');
        wp_set_current_user(0);

        $request = new WP_REST_Request('PUT', '/quick-order/v1/orders/QO-APIKEY-001/status');
        $request->set_header('X-API-Key', $apiKey);
        $request->set_param('status', 'completed');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }

    // ── Bearer Token ──

    public function test_bearer_token_grants_access()
    {
        $apiKey = 'bearer-test-key';
        update_option('quick_order_api_key', $apiKey);
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('Authorization', 'Bearer '.$apiKey);
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
    }

    public function test_bearer_token_wrong_key_is_rejected()
    {
        update_option('quick_order_api_key', 'correct-key');
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('Authorization', 'Bearer wrong-key');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status());
    }

    public function test_bearer_token_from_filter_grants_access()
    {
        add_filter('quick_order_api_key', function () {
            return 'filter-bearer-key';
        });
        delete_option('quick_order_api_key');
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('Authorization', 'Bearer filter-bearer-key');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());

        remove_all_filters('quick_order_api_key');
    }

    public function test_x_api_key_still_works_alongside_bearer()
    {
        $apiKey = 'shared-key';
        update_option('quick_order_api_key', $apiKey);
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_header('X-API-Key', $apiKey);
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
    }

    // ── Customer IP ──

    public function test_create_order_stores_specified_customer_ip()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 100);
        $request->set_param('customer_ip', '1.2.3.4');

        $response = rest_do_request($request);
        $data     = $response->get_data();
        $order    = wc_get_order($data['order_id']);

        $this->assertEquals('1.2.3.4', $order->get_customer_ip_address());
    }

    public function test_sync_order_stores_specified_customer_ip()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders/sync');
        $request->set_param('amount', 100);
        $request->set_param('transaction_id', 'IP-TEST-001');
        $request->set_param('customer_ip', '9.8.7.6');

        $response = rest_do_request($request);
        $data     = $response->get_data();
        $order    = wc_get_order($data['order_id']);

        $this->assertEquals('9.8.7.6', $order->get_customer_ip_address());
    }

    public function test_create_order_without_customer_ip_still_works()
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $request = new WP_REST_Request('POST', '/quick-order/v1/orders');
        $request->set_param('amount', 100);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
    }
}
