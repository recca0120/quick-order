<?php

namespace Suspended\QuickOrder;

class Admin
{
    /** @var OrderService */
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function register()
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('wp_ajax_quick_order_create', [$this, 'ajaxCreateOrder']);
    }

    public function addMenu()
    {
        add_submenu_page(
            'woocommerce',
            __('Quick Order', 'quick-order'),
            __('Quick Order', 'quick-order'),
            'manage_woocommerce',
            'quick-order',
            [$this, 'renderPage']
        );

        add_submenu_page(
            'woocommerce',
            __('Quick Order 設定', 'quick-order'),
            __('Quick Order 設定', 'quick-order'),
            'manage_woocommerce',
            'quick-order-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings()
    {
        register_setting('quick_order_settings', 'quick_order_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_section(
            'quick_order_api_section',
            __('API 設定', 'quick-order'),
            null,
            'quick-order-settings'
        );

        add_settings_field(
            'quick_order_api_key',
            __('API Key', 'quick-order'),
            [$this, 'renderApiKeyField'],
            'quick-order-settings',
            'quick_order_api_section'
        );
    }

    public function renderApiKeyField()
    {
        $value = get_option('quick_order_api_key', '');
        echo '<input type="text" name="quick_order_api_key" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function renderSettingsPage()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Quick Order 設定', 'quick-order') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('quick_order_settings');
        do_settings_sections('quick-order-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function renderPage()
    {
        $this->enqueueAssets();
        echo '<div class="wrap"><h1>' . esc_html__('Quick Order', 'quick-order') . '</h1>';
        echo '<div id="quick-order-app">';
        $this->renderForm();
        echo '</div></div>';
    }

    public function renderForm()
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

    public function ajaxCreateOrder()
    {
        check_ajax_referer('quick_order_create', 'quick_order_nonce');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('權限不足', 'quick-order')], 403);
        }

        try {
            $order = $this->orderService->createOrder(
                isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
                isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
                isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : ''
            );

            wp_send_json_success([
                'order_id' => $order->get_id(),
                'payment_url' => $order->get_checkout_payment_url(),
                'total' => $order->get_total(),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }

    private function enqueueAssets()
    {
        $pluginUrl = plugin_dir_url(dirname(__FILE__));

        wp_enqueue_style('quick-order', $pluginUrl . 'assets/quick-order.css', [], '1.0.0');
        wp_enqueue_script('quick-order', $pluginUrl . 'assets/quick-order.js', ['jquery'], '1.0.0', true);
        wp_localize_script('quick-order', 'quickOrder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }
}
