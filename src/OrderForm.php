<?php

namespace Suspended\QuickOrder;

class OrderForm
{
    public function enqueueAssets()
    {
        $pluginUrl = plugin_dir_url(dirname(__FILE__));

        wp_enqueue_style('quick-order', $pluginUrl.'assets/quick-order.css', [], '1.0.0');
        wp_enqueue_script('quick-order', $pluginUrl.'assets/quick-order.js', ['jquery'], '1.0.0', true);
        wp_localize_script('quick-order', 'quickOrder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function render()
    {
        ?>
        <form id="quick-order-form" class="quick-order-form">
            <?php wp_nonce_field('quick_order_create', 'quick_order_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="qo-amount"><?php esc_html_e('金額', 'quick-order'); ?> <span class="required">*</span></label></th>
                    <td><input type="number" id="qo-amount" name="amount" step="0.01" min="0.01" required class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="qo-name"><?php esc_html_e('商品名稱', 'quick-order'); ?></label></th>
                    <td><input type="text" id="qo-name" name="name" class="regular-text" placeholder="<?php esc_attr_e('自訂訂單', 'quick-order'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="qo-note"><?php esc_html_e('備註', 'quick-order'); ?></label></th>
                    <td><textarea id="qo-note" name="note" class="large-text" rows="3"></textarea></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e('建立訂單', 'quick-order'); ?></button>
            </p>
        </form>
        <div id="quick-order-result" class="quick-order-result" style="display:none;">
            <div class="notice notice-success">
                <p><?php esc_html_e('訂單建立成功！', 'quick-order'); ?></p>
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
        </div>
        <?php
    }
}
