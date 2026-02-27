<?php

namespace Suspended\QuickOrder;

class OrderService
{
    public function createOrder($amount, $name = '', $note = '')
    {
        $amount = floatval($amount);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金額必須大於 0');
        }

        $name = $name ?: '自訂訂單';

        $order = wc_create_order();

        $fee = new \WC_Order_Item_Fee();
        $fee->set_name($name);
        $fee->set_amount($amount);
        $fee->set_total($amount);
        $order->add_item($fee);

        $order->calculate_totals();
        $order->set_status('pending');

        if ($note) {
            $order->add_order_note($note);
        }

        $order->save();

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

    public function getPaymentUrl($orderId)
    {
        $order = wc_get_order($orderId);
        if (! $order) {
            throw new \InvalidArgumentException('找不到訂單');
        }

        return $order->get_checkout_payment_url();
    }
}
