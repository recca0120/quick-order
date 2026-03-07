<?php

namespace Recca0120\QuickOrder;

class OrderSyncer
{
    private const STATUS_MAP = [
        'new' => 'pending',
    ];

    private const KNOWN_FIELDS = [
        'transaction_reference', 'transaction_id', 'gateway_name',
        'payment_method', 'amount', 'description', 'status',
        'created_at', 'completed_at', 'name', 'email', 'phone_number',
    ];

    /** @var OrderService */
    private $orderService;

    /** @var \WC_Order|null */
    private $lastOrder;

    public function __construct(?OrderService $orderService = null)
    {
        $this->orderService = $orderService ?: new OrderService();
    }

    public function sync(string $headerValue): void
    {
        $data = $this->parseHeader($headerValue);

        if ($data !== null) {
            $this->syncOrder($data);
        }
    }

    /** @return \WC_Order|null */
    public function getLastOrder()
    {
        return $this->lastOrder;
    }

    /** @return array|null */
    public function parseHeader(string $value)
    {
        if ($value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    public function resolveGatewayId(string $gatewayName, string $paymentMethod): string
    {
        if ($gatewayName === 'bank-transfer') {
            return 'omnipay_banktransfer';
        }

        return 'omnipay_'.$gatewayName.'_'.$paymentMethod;
    }

    /** @return \WC_Order|null */
    private function findOrderByOrderNumber(string $orderNumber)
    {
        if ($orderNumber === '') {
            return null;
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => '_order_number',
            'meta_value' => $orderNumber,
        ]);

        return $orders[0] ?? null;
    }

    private function syncOrder(array $data): void
    {
        $orderNumber = $data['transaction_id'] ?? '';
        $rawStatus = $data['status'] ?? 'new';
        $status = self::STATUS_MAP[$rawStatus] ?? $rawStatus;

        $existing = $this->findOrderByOrderNumber($orderNumber);
        $order = $existing !== null
            ? $existing
            : $this->buildOrder($data, $orderNumber);

        $this->applyOrderFields($order, $data);
        $order->set_status($status);
        $order->save();

        $this->lastOrder = $order;
    }

    private function buildOrder(array $data, string $orderNumber): \WC_Order
    {
        $customer = Customer::fromArray([
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone_number' => $data['phone_number'] ?? '',
        ]);

        return $this->orderService->createOrder(
            $data['amount'] ?? 0,
            $data['description'] ?? '商品',
            '',
            $customer,
            $orderNumber
        );
    }

    private function applyOrderFields(\WC_Order $order, array $data): void
    {
        $reference = $data['transaction_reference'] ?? '';
        if ($reference !== '') {
            $order->set_transaction_id($reference);
        }

        $this->applyPaymentMethod($order, $data);
        $this->applyExtraFields($order, $data);
    }

    private function applyPaymentMethod(\WC_Order $order, array $data): void
    {
        $gatewayName = $data['gateway_name'] ?? '';
        $paymentMethod = $data['payment_method'] ?? '';

        if ($gatewayName !== '') {
            $order->set_payment_method($this->resolveGatewayId($gatewayName, $paymentMethod));
            $order->set_payment_method_title($gatewayName);
        }
    }

    private function applyExtraFields(\WC_Order $order, array $data): void
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::KNOWN_FIELDS, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $order->update_meta_data('_payment_'.$key, $value);
        }
    }
}
