<?php

namespace Suspended\QuickOrder\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Suspended\QuickOrder\OrderService;

abstract class AbstractCreateOrder implements MiddlewareInterface
{
    /** @var int */
    public $priority = 0;

    /** @var OrderService */
    protected $orderService;

    public function __construct(OrderService $orderService = null)
    {
        $this->orderService = $orderService ?: new OrderService();
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $data = $this->extractOrderData($request);

        $order = $this->orderService->createOrder(
            $data['amount'],
            $data['name'] ?? '',
            $data['note'] ?? ''
        );

        $request = $this->injectOrderToRequest($request, $order);

        return $next($request);
    }

    /**
     * @return array{amount: float, name?: string, note?: string}
     */
    abstract protected function extractOrderData(ServerRequestInterface $request): array;

    abstract protected function injectOrderToRequest(ServerRequestInterface $request, \WC_Order $order): ServerRequestInterface;
}
