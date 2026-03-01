<?php

namespace Suspended\QuickOrder;

class OrderService
{
    public function createOrder($amount, $name = '', $note = '', $customer = [])
    {
        $amount = floatval($amount);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金額必須大於 0');
        }

        $name = $name ?: '自訂訂單';

        $order = wc_create_order();

        $fee = new \WC_Order_Item_Fee;
        $fee->set_name($name);
        $fee->set_amount($amount);
        $fee->set_total($amount);
        $order->add_item($fee);

        $this->applyCustomer($order, $customer);

        $order->calculate_totals();
        $order->set_status('pending');

        if ($note) {
            $order->add_order_note($note);
        }

        $order->save();

        return $order;
    }

    private function applyCustomer(\WC_Order $order, array $customer)
    {
        $email = isset($customer['email']) ? sanitize_email($customer['email']) : '';
        if (! $email) {
            return;
        }

        // Link or create customer
        if (email_exists($email)) {
            $user = get_user_by('email', $email);
            $order->set_customer_id($user->ID);
        } elseif (get_option('quick_order_auto_create_customer', 'yes') === 'yes') {
            try {
                $userId = wc_create_new_customer(
                    $email,
                    '',
                    wp_generate_password(),
                    [
                        'first_name' => isset($customer['first_name']) ? $customer['first_name'] : '',
                        'last_name' => isset($customer['last_name']) ? $customer['last_name'] : '',
                    ]
                );
                $order->set_customer_id($userId);
            } catch (\Exception $e) {
                // Failed to create customer, continue as guest
            }
        }

        // Set billing fields
        $billingFields = [
            'email' => 'set_billing_email',
            'first_name' => 'set_billing_first_name',
            'last_name' => 'set_billing_last_name',
            'phone' => 'set_billing_phone',
            'address_1' => 'set_billing_address_1',
            'city' => 'set_billing_city',
            'postcode' => 'set_billing_postcode',
        ];

        foreach ($billingFields as $key => $method) {
            if (isset($customer[$key]) && $customer[$key] !== '') {
                $order->$method($customer[$key]);
            }
        }
    }

    public function getOrder($orderId)
    {
        $order = wc_get_order($orderId);
        if (! $order) {
            throw new \InvalidArgumentException('找不到訂單');
        }

        return $order;
    }

    public function updateOrderStatus($orderId, $status, $note = '')
    {
        $order = wc_get_order($orderId);
        if (! $order) {
            throw new \InvalidArgumentException('找不到訂單');
        }

        $order->set_status($status, $note);
        $order->save();

        return $order;
    }
}
