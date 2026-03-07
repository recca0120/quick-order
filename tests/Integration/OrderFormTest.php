<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\OrderForm;
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
        $this->assertStringContainsString('qo-description', $html);
        $this->assertStringContainsString('qo-note', $html);
        $this->assertStringContainsString('quick_order_nonce', $html);
    }

    public function test_render_contains_customer_fields()
    {
        $form = new OrderForm();

        ob_start();
        $form->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('qo-customer-name', $html);
        $this->assertStringContainsString('qo-email', $html);
        $this->assertStringContainsString('qo-phone', $html);
        $this->assertStringContainsString('qo-address', $html);
        $this->assertStringContainsString('qo-city', $html);
        $this->assertStringContainsString('qo-postcode', $html);
    }

    public function test_render_contains_order_number_field()
    {
        $form = new OrderForm();

        ob_start();
        $form->render();
        $html = ob_get_clean();

        $this->assertStringContainsString('qo-order-number', $html);
        // Should not be required
        preg_match('/<input[^>]*id="qo-order-number"[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('required', $matches[0]);
    }

    public function test_email_field_is_not_required()
    {
        $form = new OrderForm();

        ob_start();
        $form->render();
        $html = ob_get_clean();

        // Extract the email input tag
        preg_match('/<input[^>]*id="qo-email"[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringNotContainsString('required', $matches[0]);
    }
}
