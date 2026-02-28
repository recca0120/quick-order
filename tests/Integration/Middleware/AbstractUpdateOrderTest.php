<?php

namespace Suspended\QuickOrder\Tests\Integration\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Suspended\QuickOrder\Middleware\AbstractUpdateOrder;
use Suspended\QuickOrder\OrderService;
use WP_UnitTestCase;

class AbstractUpdateOrderTest extends WP_UnitTestCase
{
    public function test_updates_order_status_from_callback()
    {
        $orderService = new OrderService;
        $order = $orderService->createOrder(200);
        $orderId = $order->get_id();

        $middleware = new class($orderService) extends AbstractUpdateOrder
        {
            protected function extractOrderId(ServerRequestInterface $request, ResponseInterface $response)
            {
                $body = json_decode((string) $request->getBody(), true);

                return $body['order_id'] ?? null;
            }

            protected function extractStatus(ServerRequestInterface $request, ResponseInterface $response)
            {
                $body = json_decode((string) $request->getBody(), true);

                return $body['status'] ?? null;
            }
        };

        $request = new ServerRequest('POST', '/api/webhook', [], json_encode([
            'order_id' => $orderId,
            'status' => 'processing',
        ]));

        $response = $middleware->process($request, function () {
            return new Response(200);
        });

        $this->assertEquals(200, $response->getStatusCode());

        $updatedOrder = wc_get_order($orderId);
        $this->assertEquals('processing', $updatedOrder->get_status());
    }

    public function test_skips_update_when_order_id_is_null()
    {
        $orderService = new OrderService;

        $middleware = new class($orderService) extends AbstractUpdateOrder
        {
            protected function extractOrderId(ServerRequestInterface $request, ResponseInterface $response)
            {
                return null;
            }

            protected function extractStatus(ServerRequestInterface $request, ResponseInterface $response)
            {
                return 'completed';
            }
        };

        $request = new ServerRequest('POST', '/api/webhook', [], '{}');

        $response = $middleware->process($request, function () {
            return new Response(200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
