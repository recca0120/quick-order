<?php

namespace Suspended\QuickOrder\Tests\Integration;

use Suspended\QuickOrder\OrderForm;
use WP_UnitTestCase;

class OrderFormTest extends WP_UnitTestCase
{
    public function test_render_contains_required_fields()
    {
        $form = new OrderForm();

        ob_start();
        $form->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('qo-amount', $html);
        $this->assertStringContainsString('qo-name', $html);
        $this->assertStringContainsString('qo-note', $html);
        $this->assertStringContainsString('quick_order_nonce', $html);
    }
}
