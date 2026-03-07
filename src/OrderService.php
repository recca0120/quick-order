<?php

namespace Recca0120\QuickOrder;

class OrderService
{
    public function createOrder($amount, $description = '自訂訂單', $note = '', ?Customer $customer = null, $orderNumber = '')
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

        $order->calculate_totals();
        $order->set_status('pending');

        if ($note) {
            $order->add_order_note($note);
        }

        $order->save();

        return $order;
    }

    public function getOrder($orderId)
    {
        return $this->findOrFail($orderId);
    }

    public function updateOrderStatus($orderId, $status, $note = '')
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
        } elseif (get_option('quick_order_auto_create_customer', 'yes') === 'yes') {
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
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'phone' => $customer->phone_number,
            'address_1' => $customer->address_1,
            'city' => $customer->city,
            'postcode' => $customer->postcode,
        ];

        foreach ($billingFields as $field => $value) {
            $value = $field === 'email' ? $value : sanitize_text_field($value);
            if ($value !== '') {
                $order->{'set_billing_'.$field}($value);
            }
        }
    }

    private function applyOrderNumber(\WC_Order $order, $orderNumber)
    {
        if ($orderNumber === '') {
            $orderNumber = $this->generateOrderNumber();
        }

        $order->update_meta_data('_order_number', $orderNumber);
    }

    private function generateOrderNumber()
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

    private function cleanupStaleSequenceOptions($today)
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

    private function findOrFail($orderId)
    {
        $order = wc_get_order($orderId);
        if (! $order) {
            throw new \InvalidArgumentException('找不到訂單');
        }

        return $order;
    }
}
