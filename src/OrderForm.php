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
                    <th><label for="qo-email"><?php esc_html_e('Email', 'quick-order'); ?> <span class="required">*</span></label></th>
                    <td><input type="email" id="qo-email" name="email" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="qo-first-name"><?php esc_html_e('名字', 'quick-order'); ?></label></th>
                    <td><input type="text" id="qo-first-name" name="first_name" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="qo-last-name"><?php esc_html_e('姓氏', 'quick-order'); ?></label></th>
                    <td><input type="text" id="qo-last-name" name="last_name" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="qo-phone"><?php esc_html_e('電話', 'quick-order'); ?></label></th>
                    <td><input type="tel" id="qo-phone" name="phone" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="qo-address"><?php esc_html_e('地址', 'quick-order'); ?></label></th>
                    <td><input type="text" id="qo-address" name="address_1" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="qo-city"><?php esc_html_e('城市', 'quick-order'); ?></label></th>
                    <td><input type="text" id="qo-city" name="city" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="qo-postcode"><?php esc_html_e('郵遞區號', 'quick-order'); ?></label></th>
                    <td><input type="text" id="qo-postcode" name="postcode" class="regular-text"></td>
                </tr>
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
        <?php
    }
}
