<?php

namespace Recca0120\QuickOrder;

class OrderService
{
    public function createOrder(float $amount, string $description = '自訂訂單', string $note = '', ?Customer $customer = null, string $orderNumber = '', string $status = 'pending'): \WC_Order
    {
        $amount = floatval($amount);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金額必須大於 0');
        }

        $order = wc_create_order();

        $fee = new \WC_Order_Item_Fee();
        $fee->set_name($description);
        $fee->set_amount($amount);
        $fee->set_total($amount);
        $order->add_item($fee);

        if ($customer !== null) {
            $this->applyCustomer($order, $customer);
        }
        $this->applyOrderNumber($order, $orderNumber);
        $this->applySerialNumber($order);

        $order->calculate_totals();
        $order->set_status($status);

        if ($note) {
            $order->add_order_note($note);
        }

        $order->save();

        return $order;
    }

    public function linkOrdersByEmail(string $email): int
    {
        $email = sanitize_email($email);
        if (! $email) {
            return 0;
        }

        $user = get_user_by('email', $email);
        if (! $user) {
            return 0;
        }

        $orders = wc_get_orders([
            'limit' => -1,
            'type' => 'shop_order',
            'billing_email' => $email,
        ]);

        $linked = 0;
        foreach ($orders as $order) {
            if ($order->get_customer_id() === 0) {
                $order->set_customer_id($user->ID);
                $order->save();
                $linked++;
            }
        }

        return $linked;
    }

    public function getOrder(int $orderId): \WC_Order
    {
        return $this->findOrFail($orderId);
    }

    public function updateOrderStatus(int $orderId, string $status, string $note = ''): \WC_Order
    {
        $order = $this->findOrFail($orderId);
        $order->set_status($status, $note);
        $order->save();

        return $order;
    }

    private function applyCustomer(\WC_Order $order, Customer $customer)
    {
        $email = sanitize_email($customer->email);
        if (! $email) {
            return;
        }

        $nameParts = $customer->splitName();

        if (email_exists($email)) {
            $user = get_user_by('email', $email);
            $order->set_customer_id($user->ID);
        } elseif (Config::autoCreateCustomer() === 'yes') {
            try {
                $userId = wc_create_new_customer(
                    $email,
                    '',
                    $customer->phone_number ?: wp_generate_password(),
                    [
                        'first_name' => $nameParts['first_name'],
                        'last_name' => $nameParts['last_name'],
                    ]
                );
                $order->set_customer_id($userId);
            } catch (\Exception $e) {
                // Failed to create customer, continue as guest
            }
        }

        $billingFields = [
            'email' => $email,
            'first_name' => sanitize_text_field($nameParts['first_name']),
            'last_name' => sanitize_text_field($nameParts['last_name']),
            'phone' => sanitize_text_field($customer->phone_number),
            'address_1' => sanitize_text_field($customer->address_1),
            'city' => sanitize_text_field($customer->city),
            'postcode' => sanitize_text_field($customer->postcode),
        ];

        foreach ($billingFields as $field => $value) {
            if ($value !== '') {
                $order->{'set_billing_'.$field}($value);
            }
        }
    }

    private function applySerialNumber(\WC_Order $order): void
    {
        if (get_option('quick_order_serial_enabled', 'no') !== 'yes') {
            return;
        }

        $salt = Config::serialSalt();
        $orderNumber = $order->get_meta('_order_number');

        if ($orderNumber === '' || $salt === '') {
            return;
        }

        $order->update_meta_data('_serial_number', SerialNumber::generate($orderNumber, $salt));
    }

    private function applyOrderNumber(\WC_Order $order, string $orderNumber): void
    {
        if ($orderNumber === '') {
            $orderNumber = $this->generateOrderNumber();
        }

        $order->update_meta_data('_order_number', $orderNumber);
    }

    private function generateOrderNumber(): string
    {
        global $wpdb;

        $prefix = get_option('quick_order_order_prefix', 'QO');
        $today = current_time('Ymd');
        $optionKey = 'quick_order_daily_seq_'.$today;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, 1, 'no')
             ON DUPLICATE KEY UPDATE option_value = option_value + 1",
            $optionKey
        ));

        wp_cache_delete($optionKey, 'options');
        $seq = (int) get_option($optionKey);

        if (wp_rand(1, 10) === 1) {
            $this->cleanupStaleSequenceOptions($today);
        }

        return $prefix.'-'.$today.'-'.str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    private function cleanupStaleSequenceOptions(string $today): void
    {
        global $wpdb;

        $likePrefix = $wpdb->esc_like('quick_order_daily_seq_').'%';
        $staleKeys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name != %s",
            $likePrefix,
            'quick_order_daily_seq_'.$today
        ));

        if (empty($staleKeys)) {
            return;
        }

        foreach ($staleKeys as $key) {
            delete_option($key);
        }
    }

    private function findOrFail(int $orderId): \WC_Order
    {
        $order = wc_get_order($orderId);
        if (! $order) {
            throw new \InvalidArgumentException('找不到訂單');
        }

        return $order;
    }
}
