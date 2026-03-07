<?php

namespace Suspended\QuickOrder\Tests\Integration\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ServerRequestInterface;
use Suspended\QuickOrder\Middleware\AbstractCreateOrder;
use Suspended\QuickOrder\OrderService;
use WP_UnitTestCase;

/**
 * @group reverse-proxy
 */
class AbstractCreateOrderTest extends WP_UnitTestCase
{
    public function test_creates_order_and_injects_into_request()
    {
        $middleware = new class(new OrderService) extends AbstractCreateOrder
        {
            public $capturedRequest;

            protected function extractOrderData(ServerRequestInterface $request): array
            {
                $body = json_decode((string) $request->getBody(), true);

                return ['amount' => $body['amount'], 'name' => $body['name'] ?? ''];
            }

            protected function injectOrderToRequest(ServerRequestInterface $request, \WC_Order $order): ServerRequestInterface
            {
                $body = json_decode((string) $request->getBody(), true) ?: [];
                $body['wc_order_id'] = $order->get_id();
                $this->capturedRequest = $request->withBody(Stream::create(json_encode($body)));

                return $this->capturedRequest;
            }
        };

        $request = new ServerRequest('POST', '/api/orders', ['Content-Type' => 'application/json'], json_encode(['amount' => 350, 'name' => '測試']));

        $response = $middleware->process($request, function ($req) {
            $body = json_decode((string) $req->getBody(), true);
            $this->assertArrayHasKey('wc_order_id', $body);
            $this->assertIsInt($body['wc_order_id']);

            return new Response(200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_creates_order_with_customer_and_order_number()
    {
        $middleware = new class(new OrderService) extends AbstractCreateOrder
        {
            protected function extractOrderData(ServerRequestInterface $request): array
            {
                $body = json_decode((string) $request->getBody(), true);

                return [
                    'amount' => $body['amount'],
                    'name' => $body['name'] ?? '',
                    'note' => $body['note'] ?? '',
                    'customer' => $body['customer'] ?? [],
                    'order_number' => $body['order_number'] ?? '',
                ];
            }

            protected function injectOrderToRequest(ServerRequestInterface $request, \WC_Order $order): ServerRequestInterface
            {
                return $request;
            }
        };

        $payload = json_encode([
            'amount' => 200,
            'customer' => [
                'email' => 'middleware@example.com',
                'first_name' => 'Mid',
            ],
            'order_number' => 'MW-001',
        ]);

        $capturedOrder = null;
        $request = new ServerRequest('POST', '/api/orders', ['Content-Type' => 'application/json'], $payload);

        $middleware->process($request, function ($req) use (&$capturedOrder) {
            return new Response(200);
        });

        // Verify the last created order has customer and order number
        $orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        $order = $orders[0];

        $this->assertEquals('middleware@example.com', $order->get_billing_email());
        $this->assertEquals('Mid', $order->get_billing_first_name());
        $this->assertEquals('MW-001', $order->get_meta('_order_number'));
    }
}
