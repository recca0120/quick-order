<?php

namespace Recca0120\QuickOrder;

class RestApi
{
    /** @var OrderSyncer */
    private $orderSyncer;

    public function __construct(?OrderSyncer $orderSyncer = null)
    {
        $this->orderSyncer = $orderSyncer ?: new OrderSyncer();
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
                    'description' => [
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
                ] + $this->customerArgs() + $this->paymentArgs() + [
                    'order_number' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => '',
                        'sanitize_callback' => 'sanitize_text_field',
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

        register_rest_route('quick-order/v1', '/orders/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'syncOrder'],
            'permission_callback' => [$this, 'checkPermission'],
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

        register_rest_route('quick-order/v1', '/customers/link-orders', [
            'methods' => 'POST',
            'callback' => [$this, 'linkCustomerOrders'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        register_rest_route('quick-order/v1', '/orders/(?P<transaction_id>[^/]+)/status', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'transaction_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
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

    public function checkPermission(\WP_REST_Request $request)
    {
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        $apiKey = $request->get_header('X-API-Key') ?: $this->extractBearerToken($request);
        if ($apiKey) {
            $storedKey = Config::apiKey();
            if ($storedKey && hash_equals($storedKey, $apiKey)) {
                return true;
            }
        }

        return new \WP_Error('rest_forbidden', '權限不足', ['status' => 403]);
    }

    public function createOrder(\WP_REST_Request $request)
    {
        try {
            $params = $request->get_params();
            $params['transaction_id'] = $params['order_number'] ?? '';

            $order = $this->orderSyncer->sync($params);

            return new \WP_REST_Response($this->formatOrder($order), 201);
        } catch (\Exception $e) {
            return new \WP_Error('create_order_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function syncOrder(\WP_REST_Request $request)
    {
        try {
            $data = $request->get_json_params() ?: $request->get_params();
            $order = $this->orderSyncer->sync($data);

            return new \WP_REST_Response($this->formatOrder($order));
        } catch (\Exception $e) {
            return new \WP_Error('sync_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function getOrder(\WP_REST_Request $request)
    {
        try {
            $order = $this->orderSyncer->getOrder($request->get_param('id'));

            return new \WP_REST_Response($this->formatOrder($order));
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('order_not_found', $e->getMessage(), ['status' => 404]);
        }
    }

    public function updateStatus(\WP_REST_Request $request)
    {
        try {
            $order = $this->orderSyncer->updateStatus(
                $request->get_param('transaction_id'),
                $request->get_param('status'),
                $request->get_param('note') ?: ''
            );

            return new \WP_REST_Response($this->formatOrder($order));
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('order_not_found', $e->getMessage(), ['status' => 404]);
        }
    }

    public function linkCustomerOrders(\WP_REST_Request $request)
    {
        $linked = $this->orderSyncer->linkOrdersByEmail($request->get_param('email'));

        return new \WP_REST_Response(['linked' => $linked]);
    }

    public function listOrders(\WP_REST_Request $request)
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

    private function extractBearerToken(\WP_REST_Request $request): string
    {
        $auth = $request->get_header('Authorization');
        if ($auth && strpos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }

        return '';
    }

    private function customerArgs(): array
    {
        return $this->stringArgs(['name', 'email', 'phone_number', 'address_1', 'city', 'postcode']);
    }

    private function paymentArgs(): array
    {
        return $this->stringArgs(['transaction_reference', 'gateway_name', 'payment_method', 'created_at', 'completed_at', 'customer_ip', 'created_via']);
    }

    private function stringArgs(array $fields): array
    {
        $args = [];
        foreach ($fields as $field) {
            $args[$field] = [
                'required' => false,
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => $field === 'email' ? 'sanitize_email' : 'sanitize_text_field',
            ];
        }

        return $args;
    }

    private function formatOrder(\WC_Order $order): array
    {
        return [
            'order_id' => $order->get_id(),
            'order_number' => $order->get_meta('_order_number') ?: '',
            'payment_url' => $order->get_checkout_payment_url(),
            'total' => $order->get_total(),
            'status' => $order->get_status(),
        ];
    }
}
