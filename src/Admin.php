<?php

namespace Suspended\QuickOrder;

class Admin
{
    /** @var OrderService */
    private $orderService;

    /** @var OrderForm */
    private $orderForm;

    public function __construct(OrderService $orderService, OrderForm $orderForm)
    {
        $this->orderService = $orderService;
        $this->orderForm = $orderForm;
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
        $constantKey = Config::apiKeyFromConstant();
        if ($constantKey) {
            $masked = str_repeat('*', strlen($constantKey));
            echo '<input type="text" value="'.esc_attr($masked).'" class="regular-text" disabled>';
            echo '<p class="description">'.esc_html__('API Key 已透過常數 QUICK_ORDER_API_KEY 設定', 'quick-order').'</p>';

            return;
        }

        $value = get_option('quick_order_api_key', '');
        echo '<input type="text" name="quick_order_api_key" value="'.esc_attr($value).'" class="regular-text">';
    }

    public function renderPage()
    {
        $this->orderForm->enqueueAssets();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Quick Order', 'quick-order'); ?></h1>
            <nav class="nav-tab-wrapper">
                <a href="#tab-order" class="nav-tab nav-tab-active" data-tab="tab-order"><?php esc_html_e('建立訂單', 'quick-order'); ?></a>
                <a href="#tab-settings" class="nav-tab" data-tab="tab-settings"><?php esc_html_e('設定', 'quick-order'); ?></a>
            </nav>
            <div id="tab-order" class="qo-tab-panel">
                <div class="qo-card">
                    <?php $this->orderForm->render(); ?>
                </div>
                <div id="quick-order-result" class="quick-order-result" style="display:none;">
                    <div class="qo-card">
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
                </div>
            </div>
            <div id="tab-settings" class="qo-tab-panel" style="display:none;">
                <div class="qo-card">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('quick_order_settings');
        do_settings_sections('quick-order-settings');
        submit_button();
        ?>
                    </form>
                </div>
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
}
