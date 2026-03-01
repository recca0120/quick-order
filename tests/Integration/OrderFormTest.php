<?php

namespace Suspended\QuickOrder\Tests\Integration;

use Suspended\QuickOrder\OrderForm;
use WP_UnitTestCase;

class OrderFormTest extends WP_UnitTestCase
{
    public function test_render_contains_required_fields()
    {
        $form = new OrderForm;

        ob_start();
        $form->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('qo-amount', $html);
        $this->assertStringContainsString('qo-name', $html);
        $this->assertStringContainsString('qo-note', $html);
        $this->assertStringContainsString('quick_order_nonce', $html);
    }

    public function test_render_contains_customer_fields()
    {
        $form = new OrderForm;

        ob_start();
        $form->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('qo-email', $html);
        $this->assertStringContainsString('qo-first-name', $html);
        $this->assertStringContainsString('qo-last-name', $html);
        $this->assertStringContainsString('qo-phone', $html);
        $this->assertStringContainsString('qo-address', $html);
        $this->assertStringContainsString('qo-city', $html);
        $this->assertStringContainsString('qo-postcode', $html);
    }

    public function test_email_field_is_not_required()
    {
        $form = new OrderForm;

        ob_start();
        $form->render();
        $html = ob_get_clean();

        // Extract the email input tag
        preg_match('/<input[^>]*id="qo-email"[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('required', $matches[0]);
    }
}
