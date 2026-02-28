<?php

namespace Suspended\QuickOrder\Tests\Integration\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ServerRequestInterface;
use Suspended\QuickOrder\Middleware\AbstractCreateOrder;
use Suspended\QuickOrder\OrderService;
use WP_UnitTestCase;

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
}
