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
}
