<?php

namespace Suspended\QuickOrder\Tests\Integration;

use Suspended\QuickOrder\OrderService;
use WP_UnitTestCase;

class OrderServiceTest extends WP_UnitTestCase
{
    /** @var OrderService */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderService;
    }

    public function test_create_order_with_amount()
    {
        $order = $this->service->createOrder(100);

        $this->assertInstanceOf(\WC_Order::class, $order);
        $this->assertEquals('100.00', $order->get_total());
        $this->assertEquals('pending', $order->get_status());
    }

    public function test_create_order_uses_default_name()
    {
        $order = $this->service->createOrder(50);

        $items = $order->get_items('fee');
        $fee = reset($items);
        $this->assertEquals('自訂訂單', $fee->get_name());
    }

    public function test_create_order_with_custom_name()
    {
        $order = $this->service->createOrder(200, '會員儲值');

        $items = $order->get_items('fee');
        $fee = reset($items);
        $this->assertEquals('會員儲值', $fee->get_name());
    }

    public function test_create_order_with_note()
    {
        $order = $this->service->createOrder(300, '', '客戶備註');

        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $noteContents = array_map(function ($n) {
            return $n->content;
        }, $notes);
        $this->assertContains('客戶備註', $noteContents);
    }

    public function test_create_order_rejects_zero_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createOrder(0);
    }

    public function test_create_order_rejects_negative_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createOrder(-10);
    }

    public function test_update_order_status()
    {
        $order = $this->service->createOrder(100);

        $updated = $this->service->updateOrderStatus($order->get_id(), 'processing', '已付款');

        $this->assertEquals('processing', $updated->get_status());
        $notes = wc_get_order_notes(['order_id' => $updated->get_id()]);
        $noteContents = array_map(function ($n) {
            return $n->content;
        }, $notes);
        $this->assertTrue(
            count(array_filter($noteContents, function ($c) {
                return strpos($c, '已付款') !== false;
            })) > 0
        );
    }

    public function test_update_order_status_with_invalid_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateOrderStatus(999999, 'completed');
    }

    public function test_get_order_returns_order()
    {
        $created = $this->service->createOrder(150);

        $order = $this->service->getOrder($created->get_id());

        $this->assertInstanceOf(\WC_Order::class, $order);
        $this->assertEquals($created->get_id(), $order->get_id());
    }

    public function test_get_order_with_invalid_id_throws()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getOrder(999999);
    }

    // ── 客戶資料 ──

    public function test_create_order_with_customer_billing_fields()
    {
        $customer = [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '0912345678',
            'address_1' => '台北市信義區',
            'city' => '台北市',
            'postcode' => '110',
        ];

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertEquals('test@example.com', $order->get_billing_email());
        $this->assertEquals('John', $order->get_billing_first_name());
        $this->assertEquals('Doe', $order->get_billing_last_name());
        $this->assertEquals('0912345678', $order->get_billing_phone());
        $this->assertEquals('台北市信義區', $order->get_billing_address_1());
        $this->assertEquals('台北市', $order->get_billing_city());
        $this->assertEquals('110', $order->get_billing_postcode());
    }

    public function test_create_order_auto_creates_customer_when_email_not_exists()
    {
        update_option('quick_order_auto_create_customer', 'yes');

        $customer = [
            'email' => 'newuser_' . wp_generate_password(4, false) . '@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
        ];

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertGreaterThan(0, $order->get_customer_id());
        $user = get_user_by('id', $order->get_customer_id());
        $this->assertEquals($customer['email'], $user->user_email);
    }

    public function test_create_order_does_not_create_customer_when_auto_create_disabled()
    {
        update_option('quick_order_auto_create_customer', 'no');

        $customer = [
            'email' => 'noauto_' . wp_generate_password(4, false) . '@example.com',
            'first_name' => 'No',
            'last_name' => 'Auto',
        ];

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertEquals(0, $order->get_customer_id());
        $this->assertEquals($customer['email'], $order->get_billing_email());
    }

    public function test_create_order_links_existing_customer_by_email()
    {
        $userId = self::factory()->user->create(['user_email' => 'existing@example.com']);

        $customer = [
            'email' => 'existing@example.com',
            'first_name' => 'Exist',
            'last_name' => 'User',
        ];

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertEquals($userId, $order->get_customer_id());
    }

    public function test_create_order_without_email_remains_guest()
    {
        $order = $this->service->createOrder(100, '', '', []);

        $this->assertEquals(0, $order->get_customer_id());
    }
}
