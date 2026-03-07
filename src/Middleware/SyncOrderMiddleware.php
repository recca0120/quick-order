<?php

namespace Recca0120\QuickOrder\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\QuickOrder\OrderSyncer;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;

class SyncOrderMiddleware implements MiddlewareInterface
{
    /** @var int */
    public $priority = 0;

    /** @var OrderSyncer */
    private $syncer;

    public function __construct(?OrderSyncer $syncer = null)
    {
        $this->syncer = $syncer ?: new OrderSyncer();
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request->withHeader('x-quick-order', '1'));

        $this->syncer->sync($response->getHeaderLine('x-quick-order'));

        return $response->withoutHeader('x-quick-order');
    }
}
