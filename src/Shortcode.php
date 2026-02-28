<?php

namespace Suspended\QuickOrder;

class Shortcode
{
    /** @var OrderForm */
    private $orderForm;

    public function __construct(OrderForm $orderForm)
    {
        $this->orderForm = $orderForm;
    }

    public function register()
    {
        add_shortcode('quick_order', [$this, 'render']);
    }

    public function render()
    {
        if (! current_user_can('manage_woocommerce')) {
            return '';
        }

        $this->orderForm->enqueueAssets();

        ob_start();
        echo '<div id="quick-order-app" class="quick-order-frontend">';
        $this->orderForm->render();
        ?>
        <div id="quick-order-result" class="quick-order-result" style="display:none;">
            <div class="notice notice-success">
                <p><?php esc_html_e('訂單建立成功！', 'quick-order'); ?></p>
            </div>
            <p><?php esc_html_e('訂單編號：', 'quick-order'); ?><strong id="qo-order-id"></strong></p>
            <p>
                <?php esc_html_e('付款連結：', 'quick-order'); ?>
                <input type="text" id="qo-payment-url" class="large-text" readonly>
            </p>
            <p>
                <button type="button" id="qo-copy-url" class="button"><?php esc_html_e('複製連結', 'quick-order'); ?></button>
                <span id="qo-copy-success" style="display:none;color:green;"><?php esc_html_e('已複製！', 'quick-order'); ?></span>
            </p>
        </div>
        <?php
        echo '</div>';

        return ob_get_clean();
    }
}
