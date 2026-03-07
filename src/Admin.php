<?php

namespace Recca0120\QuickOrder;

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
        add_action('wp_ajax_quick_order_link_customer', [$this, 'ajaxLinkCustomerOrders']);

        if (get_option('quick_order_custom_order_number', 'yes') === 'yes') {
            add_filter('woocommerce_order_number', [$this, 'filterOrderNumber'], 10, 2);
        }
    }

    public function filterOrderNumber($orderNumber, $order)
    {
        $custom = $order->get_meta('_order_number');

        return $custom ?: $orderNumber;
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
        $this->registerApiSettings();
        $this->registerCustomerSettings();
        $this->registerOrderNumberSettings();
    }

    private function registerApiSettings(): void
    {
        register_setting('quick_order_settings', 'quick_order_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_section('quick_order_api_section', __('API 設定', 'quick-order'), null, 'quick-order-settings');
        add_settings_field('quick_order_api_key', __('API Key', 'quick-order'), [$this, 'renderApiKeyField'], 'quick-order-settings', 'quick_order_api_section');
    }

    private function registerCustomerSettings(): void
    {
        register_setting('quick_order_settings', 'quick_order_auto_create_customer', [
            'type' => 'string',
            'default' => 'no',
            'sanitize_callback' => [$this, 'sanitizeCheckbox'],
        ]);

        add_settings_section('quick_order_customer_section', __('客戶設定', 'quick-order'), null, 'quick-order-settings');
        add_settings_field('quick_order_auto_create_customer', __('自動建立帳號', 'quick-order'), [$this, 'renderAutoCreateCustomerField'], 'quick-order-settings', 'quick_order_customer_section');
    }

    private function registerOrderNumberSettings(): void
    {
        register_setting('quick_order_settings', 'quick_order_custom_order_number', [
            'type' => 'string',
            'default' => 'yes',
            'sanitize_callback' => [$this, 'sanitizeCheckbox'],
        ]);

        register_setting('quick_order_settings', 'quick_order_order_prefix', [
            'type' => 'string',
            'default' => 'QO',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_section('quick_order_order_number_section', __('訂單編號設定', 'quick-order'), null, 'quick-order-settings');
        add_settings_field('quick_order_custom_order_number', __('顯示自訂編號', 'quick-order'), [$this, 'renderCustomOrderNumberField'], 'quick-order-settings', 'quick_order_order_number_section');
        add_settings_field('quick_order_order_prefix', __('編號前綴', 'quick-order'), [$this, 'renderOrderPrefixField'], 'quick-order-settings', 'quick_order_order_number_section');
    }

    private function requireAjaxPermission(string $nonce): void
    {
        check_ajax_referer($nonce, 'quick_order_nonce');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('權限不足', 'quick-order')], 403);
        }
    }

    public function sanitizeCheckbox($value)
    {
        return $value === 'yes' ? 'yes' : 'no';
    }

    public function renderAutoCreateCustomerField()
    {
        $value = get_option('quick_order_auto_create_customer', 'no');
        echo '<label><input type="checkbox" name="quick_order_auto_create_customer" value="yes"'.checked($value, 'yes', false).'>';
        esc_html_e('當客戶 Email 不存在時自動建立帳號', 'quick-order');
        echo '</label>';
    }

    public function renderCustomOrderNumberField()
    {
        $value = get_option('quick_order_custom_order_number', 'yes');
        echo '<label><input type="checkbox" name="quick_order_custom_order_number" value="yes"'.checked($value, 'yes', false).'>';
        esc_html_e('在 WooCommerce 中顯示自訂訂單編號', 'quick-order');
        echo '</label>';
    }

    public function renderOrderPrefixField()
    {
        $value = get_option('quick_order_order_prefix', 'QO');
        echo '<input type="text" name="quick_order_order_prefix" value="'.esc_attr($value).'" class="regular-text">';
        echo '<p class="description">'.esc_html__('訂單編號格式：{前綴}-{日期}-{流水號}，例如 QO-20260302-001', 'quick-order').'</p>';
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
                <a href="#tab-tools" class="nav-tab" data-tab="tab-tools"><?php esc_html_e('工具', 'quick-order'); ?></a>
            </nav>
            <div id="tab-order" class="qo-tab-panel">
                <div class="card">
                    <?php $this->orderForm->render(); ?>
                </div>
                <?php $this->orderForm->renderResult(); ?>
            </div>
            <div id="tab-tools" class="qo-tab-panel" style="display:none;">
                <div class="card">
                    <h2><?php esc_html_e('補同步客戶關聯', 'quick-order'); ?></h2>
                    <p><?php esc_html_e('輸入客戶 Email，將該 Email 的 guest 訂單補關聯到對應的帳號。', 'quick-order'); ?></p>
                    <form id="qo-link-customer-form">
                        <?php wp_nonce_field('quick_order_link_customer', 'quick_order_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="qo-link-email"><?php esc_html_e('客戶 Email', 'quick-order'); ?></label></th>
                                <td><input type="email" id="qo-link-email" name="email" class="regular-text" required></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e('補同步關聯', 'quick-order'); ?></button>
                        </p>
                    </form>
                    <div id="qo-link-result" style="display:none;"></div>
                </div>
            </div>
            <div id="tab-settings" class="qo-tab-panel" style="display:none;">
                <div class="card">
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

    public function ajaxLinkCustomerOrders()
    {
        $this->requireAjaxPermission('quick_order_link_customer');

        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (! $email) {
            wp_send_json_error(['message' => __('請輸入有效的 Email', 'quick-order')], 400);
        }

        $linked = $this->orderService->linkOrdersByEmail($email);
        wp_send_json_success(['linked' => $linked]);
    }

    public function ajaxCreateOrder()
    {
        $this->requireAjaxPermission('quick_order_create');

        try {
            $post = wp_unslash($_POST);
            $order = $this->orderService->createOrder(
                floatval($post['amount'] ?? 0),
                sanitize_text_field($post['description'] ?? ''),
                sanitize_textarea_field($post['note'] ?? ''),
                Customer::fromPost($_POST),
                sanitize_text_field($post['order_number'] ?? '')
            );

            wp_send_json_success([
                'order_id' => $order->get_id(),
                'order_number' => $order->get_meta('_order_number'),
                'payment_url' => $order->get_checkout_payment_url(),
                'total' => $order->get_total(),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }
}
