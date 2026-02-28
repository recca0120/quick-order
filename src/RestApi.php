<?php

namespace Suspended\QuickOrder;

class RestApi
{
    /** @var OrderService */
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function register()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes()
    {
        register_rest_route('quick-order/v1', '/orders', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'createOrder'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'amount' => [
                        'required' => true,
                        'type' => 'number',
                        'sanitize_callback' => function ($value) {
                            return floatval($value);
                        },
                    ],
                    'name' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'note' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                ],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'listOrders'],
                'permission_callback' => [$this, 'checkPermission'],
                'args' => [
                    'status' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        register_rest_route('quick-order/v1', '/orders/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getOrder'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('quick-order/v1', '/orders/(?P<id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'status' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'note' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);
    }

    public function checkPermission($request)
    {
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        $apiKey = $request->get_header('X-API-Key');
        if ($apiKey) {
            $storedKey = Config::apiKey();
            if ($storedKey && hash_equals($storedKey, $apiKey)) {
                return true;
            }
        }

        return new \WP_Error('rest_forbidden', '權限不足', ['status' => 403]);
    }

    public function createOrder($request)
    {
        try {
            $order = $this->orderService->createOrder(
                $request->get_param('amount'),
                $request->get_param('name'),
                $request->get_param('note')
            );

            return new \WP_REST_Response($this->formatOrder($order), 201);
        } catch (\Exception $e) {
            return new \WP_Error('create_order_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function getOrder($request)
    {
        try {
            $order = $this->orderService->getOrder($request->get_param('id'));

            return new \WP_REST_Response($this->formatOrder($order));
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('order_not_found', $e->getMessage(), ['status' => 404]);
        }
    }

    public function updateStatus($request)
    {
        try {
            $order = $this->orderService->updateOrderStatus(
                $request->get_param('id'),
                $request->get_param('status'),
                $request->get_param('note')
            );

            return new \WP_REST_Response($this->formatOrder($order));
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('order_not_found', $e->getMessage(), ['status' => 404]);
        }
    }

    public function listOrders($request)
    {
        $args = [
            'type' => 'shop_order',
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $status = $request->get_param('status');
        if ($status) {
            $args['status'] = 'wc-'.$status;
        }

        $orders = wc_get_orders($args);

        return new \WP_REST_Response(array_map([$this, 'formatOrder'], $orders));
    }

    private function formatOrder(\WC_Order $order)
    {
        return [
            'order_id' => $order->get_id(),
            'payment_url' => $order->get_checkout_payment_url(),
            'total' => $order->get_total(),
            'status' => $order->get_status(),
        ];
    }
}
