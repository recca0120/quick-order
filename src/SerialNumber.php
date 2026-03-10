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
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'displayInAdmin'], 10, 1);
    }

    public function displayInEmail(\WC_Order $order, bool $sentToAdmin, bool $plainText, $email): void
    {
        $serial = $this->getSerialIfCompleted($order);
        if ($serial === '') {
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
        $serial = $this->getSerialIfCompleted($order);
        if ($serial === '') {
            return;
        }

        $this->renderSerialHtml($serial);
    }

    public function displayInAdmin(\WC_Order $order): void
    {
        if (! $this->isDisplayEnabled($order)) {
            return;
        }

        $serial = $order->get_meta('_serial_number');
        if (! $serial) {
            return;
        }

        woocommerce_wp_text_input([
            'id'                => '_serial_number',
            'label'             => __('序號', 'quick-order'),
            'value'             => $serial,
            'class'             => 'large-text',
            'wrapper_class'     => 'form-field-wide',
            'custom_attributes' => ['readonly' => 'readonly'],
        ]);
    }

    private function getSerialIfCompleted(\WC_Order $order): string
    {
        if (! $this->isDisplayEnabled($order)) {
            return '';
        }

        if ($order->get_status() !== 'completed') {
            return '';
        }

        return (string) $order->get_meta('_serial_number');
    }

    private function isDisplayEnabled(\WC_Order $order): bool
    {
        return (bool) apply_filters('quick_order_serial_display', true, $order);
    }

    private function renderSerialHtml(string $serial): void
    {
        echo '<p><strong>'.esc_html__('序號', 'quick-order').':</strong> '.esc_html($serial).'</p>';
    }
}
