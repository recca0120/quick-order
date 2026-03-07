<?php

namespace Recca0120\QuickOrder\Tests\Integration\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Recca0120\QuickOrder\Middleware\SyncOrderMiddleware;
use Recca0120\QuickOrder\OrderSyncer;
use WP_UnitTestCase;

/**
 * @group reverse-proxy
 */
class SyncOrderMiddlewareTest extends WP_UnitTestCase
{
    public function test_adds_x_quick_order_header_to_request()
    {
        $middleware = new SyncOrderMiddleware();
        $request = new ServerRequest('POST', '/api/pay');
        $capturedRequest = null;

        $middleware->process($request, function ($req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response(200);
        });

        $this->assertEquals('1', $capturedRequest->getHeaderLine('x-quick-order'));
    }

    public function test_delegates_to_order_syncer_and_strips_header()
    {
        $syncer = $this->createMock(OrderSyncer::class);
        $request = new ServerRequest('POST', '/api/pay');
        $headerValue = base64_encode(json_encode(['status' => 'new']));
        $response = new Response(200, ['x-quick-order' => $headerValue, 'content-type' => 'application/json']);

        $syncer->expects($this->once())
            ->method('sync')
            ->with($headerValue);

        $middleware = new SyncOrderMiddleware($syncer);
        $result = $middleware->process($request, function () use ($response) {
            return $response;
        });

        $this->assertFalse($result->hasHeader('x-quick-order'));
        $this->assertTrue($result->hasHeader('content-type'));
    }
}
