<?php

namespace Suspended\QuickOrder;

class Shortcode
{
    /** @var Admin */
    private $admin;

    public function __construct(Admin $admin)
    {
        $this->admin = $admin;
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

        $this->enqueueAssets();

        ob_start();
        echo '<div id="quick-order-app" class="quick-order-frontend">';
        $this->admin->renderForm();
        echo '</div>';

        return ob_get_clean();
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
