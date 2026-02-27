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
        echo '</div>';

        return ob_get_clean();
    }
}
