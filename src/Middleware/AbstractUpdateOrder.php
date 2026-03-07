<?php

namespace Suspended\QuickOrder\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Recca0120\ReverseProxy\Contracts\MiddlewareInterface;
use Suspended\QuickOrder\OrderService;

abstract class AbstractUpdateOrder implements MiddlewareInterface
{
    /** @var int */
    public $priority = 0;

    /** @var OrderService */
    protected $orderService;

    public function __construct(?OrderService $orderService = null)
    {
        $this->orderService = $orderService ?: new OrderService;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);

        $orderId = $this->extractOrderId($request, $response);
        $status = $this->extractStatus($request, $response);

        if ($orderId && $status) {
            $note = $this->extractNote($request, $response);
            $this->orderService->updateOrderStatus($orderId, $status, $note);
        }

        return $response;
    }

    /** @return int|null */
    abstract protected function extractOrderId(ServerRequestInterface $request, ResponseInterface $response);

    /** @return string|null */
    abstract protected function extractStatus(ServerRequestInterface $request, ResponseInterface $response);

    /** @return string */
    protected function extractNote(ServerRequestInterface $request, ResponseInterface $response)
    {
        return '';
    }
}
