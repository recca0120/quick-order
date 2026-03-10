<?php

namespace Recca0120\QuickOrder;

class OrderSyncer
{
    private const STATUS_MAP = [
        'new' => 'pending',
    ];

    private const PAYMENT_METHOD_MAP = [
        'credit_card' => 'credit',
        'web_atm'     => 'webatm',
    ];

    // Fields handled explicitly — excluded from _payment_* meta
    private const SYNC_FIELDS = [
        'transaction_id', 'status', 'amount', 'description', 'note',
        'name', 'email', 'phone_number', 'address_1', 'city', 'postcode',
        'order_number', 'created_via',
        'transaction_reference', 'customer_ip',
        'gateway_name', 'payment_method',
        'created_at', 'completed_at',
    ];

    /** @var OrderService */
    private $orderService;

    public function __construct(?OrderService $orderService = null)
    {
        $this->orderService = $orderService ?: new OrderService();
    }

    public function getOrder(int $id): \WC_Order
    {
        return $this->orderService->getOrder($id);
    }

    public function linkOrdersByEmail(string $email): int
    {
        return $this->orderService->linkOrdersByEmail($email);
    }


    public function updateStatus(string $transactionId, string $status, string $note = ''): \WC_Order
    {
        $order = $this->findOrderByOrderNumber($transactionId);
        if ($order === null) {
            throw new \InvalidArgumentException('找不到訂單');
        }

        $mapped = self::STATUS_MAP[$status] ?? $status;
        $order->set_status($mapped, $note);
        $order->save();

        return $order;
    }

    public function sync(array $data): \WC_Order
    {
        $orderNumber = $data['transaction_id'] ?? '';
        $rawStatus = $data['status'] ?? 'new';
        $status = self::STATUS_MAP[$rawStatus] ?? $rawStatus;

        $existing = $this->findOrderByOrderNumber($orderNumber);
        $order = $existing !== null
            ? $existing
            : $this->buildOrder($data, $orderNumber, $status);

        $this->applyOrderFields($order, $data);
        if ($existing !== null) {
            $order->set_status($status);
        }
        $order->save();

        return $order;
    }

    /** @return \WC_Order|null */
    public function syncFromBase64(string $headerValue)
    {
        $data = $this->parseHeader($headerValue);

        return $data !== null ? $this->sync($data) : null;
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

        $method = self::PAYMENT_METHOD_MAP[$paymentMethod] ?? $paymentMethod;

        return 'omnipay_'.$gatewayName.($method !== '' ? '_'.$method : '');
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

    private function buildOrder(array $data, string $orderNumber, string $status): \WC_Order
    {
        $options = new OrderOptions(
            $data['description'] ?? '商品',
            $data['note'] ?? '',
            Customer::fromArray($data),
            $orderNumber,
            $status,
            $data['created_via'] ?? 'checkout'
        );

        return $this->orderService->createOrder($data['amount'] ?? 0, $options);
    }

    private function applyOrderFields(\WC_Order $order, array $data): void
    {
        if (($reference = $data['transaction_reference'] ?? '') !== '') {
            $order->set_transaction_id($reference);
        }

        if (($ip = $data['customer_ip'] ?? '') !== '') {
            $order->set_customer_ip_address($ip);
        }

        $this->applyDates($order, $data);
        $this->applyRemittanceLast5($order, $data);
        $this->applyPaymentMethod($order, $data);

        $remaining = array_diff_key($data, array_flip(self::SYNC_FIELDS));
        $this->applyExtraFields($order, $remaining);
    }

    private function applyRemittanceLast5(\WC_Order $order, array $data): void
    {
        if (($data['payment_method'] ?? '') !== 'atm') {
            return;
        }

        $accountNumber = $data['account_number'] ?? '';
        if ($accountNumber === '') {
            return;
        }

        $order->update_meta_data('_omnipay_remittance_last5', substr($accountNumber, -5));
    }

    private function applyDates(\WC_Order $order, array $data): void
    {
        if (($createdAt = $data['created_at'] ?? '') !== '') {
            $order->set_date_created($createdAt);
        }

        if (($completedAt = $data['completed_at'] ?? '') !== '') {
            $order->set_date_paid($completedAt);
        }
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
            if ($value === null || $value === '') {
                continue;
            }

            $order->update_meta_data('_payment_'.$key, $value);
        }
    }
}
