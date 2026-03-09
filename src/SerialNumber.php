<?php

namespace Recca0120\QuickOrder;

class SerialNumber
{
    public static function generate(string $transactionId, string $salt): string
    {
        return strtoupper(hash('sha256', $transactionId.$salt));
    }

    public function register(): void
    {
        add_action('woocommerce_email_order_meta', [$this, 'displayInEmail'], 10, 4);
        add_action('woocommerce_order_details_after_order_table', [$this, 'displayInOrderDetails'], 10, 1);
    }

    public function displayInEmail(\WC_Order $order, bool $sentToAdmin, bool $plainText, $email): void
    {
        $serial = $order->get_meta('_serial_number');
        if (! $serial) {
            return;
        }

        if ($plainText) {
            echo esc_html__('序號', 'quick-order').': '.esc_html($serial)."\n";
        } else {
            $this->renderSerialHtml($serial);
        }
    }

    public function displayInOrderDetails(\WC_Order $order): void
    {
        $serial = $order->get_meta('_serial_number');
        if (! $serial) {
            return;
        }

        $this->renderSerialHtml($serial);
    }

    private function renderSerialHtml(string $serial): void
    {
        echo '<p><strong>'.esc_html__('序號', 'quick-order').':</strong> '.esc_html($serial).'</p>';
    }
}
