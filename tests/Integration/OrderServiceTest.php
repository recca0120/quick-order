<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\Customer;
use Recca0120\QuickOrder\OrderService;
use WP_UnitTestCase;

class OrderServiceTest extends WP_UnitTestCase
{
    /** @var OrderService */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderService();
    }

    public function test_create_order_with_amount()
    {
        $order = $this->service->createOrder(100);

        $this->assertInstanceOf(\WC_Order::class, $order);
        $this->assertEquals('100.00', $order->get_total());
        $this->assertEquals('pending', $order->get_status());
    }

    public function test_create_order_uses_default_name()
    {
        $order = $this->service->createOrder(50);

        $items = $order->get_items('fee');
        $fee = reset($items);
        $this->assertEquals('自訂訂單', $fee->get_name());
    }

    public function test_create_order_with_custom_name()
    {
        $order = $this->service->createOrder(200, '會員儲值');

        $items = $order->get_items('fee');
        $fee = reset($items);
        $this->assertEquals('會員儲值', $fee->get_name());
    }

    public function test_create_order_with_note()
    {
        $order = $this->service->createOrder(300, '', '客戶備註');

        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $noteContents = array_map(function ($n) {
            return $n->content;
        }, $notes);
        $this->assertContains('客戶備註', $noteContents);
    }

    public function test_create_order_rejects_zero_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createOrder(0);
    }

    public function test_create_order_rejects_negative_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createOrder(-10);
    }

    public function test_update_order_status()
    {
        $order = $this->service->createOrder(100);

        $updated = $this->service->updateOrderStatus($order->get_id(), 'processing', '已付款');

        $this->assertEquals('processing', $updated->get_status());
        $notes = wc_get_order_notes(['order_id' => $updated->get_id()]);
        $noteContents = array_map(function ($n) {
            return $n->content;
        }, $notes);
        $this->assertTrue(
            count(array_filter($noteContents, function ($c) {
                return strpos($c, '已付款') !== false;
            })) > 0
        );
    }

    public function test_update_order_status_with_invalid_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateOrderStatus(999999, 'completed');
    }

    public function test_get_order_returns_order()
    {
        $created = $this->service->createOrder(150);

        $order = $this->service->getOrder($created->get_id());

        $this->assertInstanceOf(\WC_Order::class, $order);
        $this->assertEquals($created->get_id(), $order->get_id());
    }

    public function test_get_order_with_invalid_id_throws()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getOrder(999999);
    }

    // ── 客戶資料 ──

    public function test_create_order_with_customer_billing_fields()
    {
        $customer = Customer::fromArray([
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'phone_number' => '0912345678',
            'address_1' => '台北市信義區',
            'city' => '台北市',
            'postcode' => '110',
        ]);

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertEquals('test@example.com', $order->get_billing_email());
        $this->assertEquals('John', $order->get_billing_first_name());
        $this->assertEquals('Doe', $order->get_billing_last_name());
        $this->assertEquals('0912345678', $order->get_billing_phone());
        $this->assertEquals('台北市信義區', $order->get_billing_address_1());
        $this->assertEquals('台北市', $order->get_billing_city());
        $this->assertEquals('110', $order->get_billing_postcode());
    }

    public function test_create_order_auto_creates_customer_when_email_not_exists()
    {
        update_option('quick_order_auto_create_customer', 'yes');

        $customer = Customer::fromArray([
            'email' => 'newuser_'.wp_generate_password(4, false).'@example.com',
            'name' => 'New User',
        ]);

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertGreaterThan(0, $order->get_customer_id());
        $user = get_user_by('id', $order->get_customer_id());
        $this->assertEquals($customer->email, $user->user_email);
    }

    public function test_create_order_does_not_create_customer_when_auto_create_disabled()
    {
        update_option('quick_order_auto_create_customer', 'no');

        $customer = Customer::fromArray([
            'email' => 'noauto_'.wp_generate_password(4, false).'@example.com',
            'name' => 'No Auto',
        ]);

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertEquals(0, $order->get_customer_id());
        $this->assertEquals($customer->email, $order->get_billing_email());
    }

    public function test_create_order_links_existing_customer_by_email()
    {
        $userId = self::factory()->user->create(['user_email' => 'existing@example.com']);

        $customer = Customer::fromArray([
            'email' => 'existing@example.com',
            'name' => 'Exist User',
        ]);

        $order = $this->service->createOrder(100, '', '', $customer);

        $this->assertEquals($userId, $order->get_customer_id());
    }

    public function test_create_order_without_email_remains_guest()
    {
        $order = $this->service->createOrder(100);

        $this->assertEquals(0, $order->get_customer_id());
    }

    public function test_create_order_sanitizes_customer_billing_fields()
    {
        $customer = Customer::fromArray([
            'email' => 'valid@example.com',
            'name' => '<script>alert("xss")</script>John',
            'phone_number' => "123\n456",
            'city' => '  台北市  ',
        ]);

        $order = $this->service->createOrder(100, '', '', $customer);

        // sanitize_text_field strips tags, trims, removes newlines
        $this->assertStringNotContainsString('<script>', $order->get_billing_first_name());
        $this->assertEquals('123 456', $order->get_billing_phone());
        $this->assertEquals('台北市', $order->get_billing_city());
    }

    public function test_create_order_sanitizes_invalid_email_in_customer()
    {
        $customer = Customer::fromArray([
            'email' => 'not-valid-email',
            'name' => 'Test',
        ]);

        $order = $this->service->createOrder(100, '', '', $customer);

        // Invalid email is sanitized to empty, so applyCustomer skips
        $this->assertEquals('', $order->get_billing_email());
        $this->assertEquals(0, $order->get_customer_id());
    }

    // ── 自訂訂單編號 ──

    public function test_create_order_generates_order_number_automatically()
    {
        $today = current_time('Ymd');

        $order = $this->service->createOrder(100);

        $orderNumber = $order->get_meta('_order_number');
        $this->assertMatchesRegularExpression('/^QO-'.$today.'-\d{3,}$/', $orderNumber);
    }

    public function test_create_order_with_custom_order_number()
    {
        $order = $this->service->createOrder(100, '', '', null, 'MY-CUSTOM-001');

        $this->assertEquals('MY-CUSTOM-001', $order->get_meta('_order_number'));
    }

    public function test_create_order_increments_daily_sequence()
    {
        $today = current_time('Ymd');

        $order1 = $this->service->createOrder(100);
        $order2 = $this->service->createOrder(200);
        $order3 = $this->service->createOrder(300);

        $this->assertStringEndsWith('-001', $order1->get_meta('_order_number'));
        $this->assertStringEndsWith('-002', $order2->get_meta('_order_number'));
        $this->assertStringEndsWith('-003', $order3->get_meta('_order_number'));
    }

    public function test_create_order_uses_custom_prefix_setting()
    {
        update_option('quick_order_order_prefix', 'INV');
        $today = current_time('Ymd');

        $order = $this->service->createOrder(100);

        $this->assertStringStartsWith('INV-'.$today, $order->get_meta('_order_number'));
    }

    public function test_create_order_number_readable_from_meta()
    {
        $order = $this->service->createOrder(100);

        $reloaded = wc_get_order($order->get_id());
        $this->assertNotEmpty($reloaded->get_meta('_order_number'));
        $this->assertEquals($order->get_meta('_order_number'), $reloaded->get_meta('_order_number'));
    }

    public function test_daily_sequence_option_matches_order_count()
    {
        $today = current_time('Ymd');
        $optionKey = 'quick_order_daily_seq_'.$today;

        $this->service->createOrder(100);
        $this->service->createOrder(200);
        $this->service->createOrder(300);

        wp_cache_delete($optionKey, 'options');
        $this->assertEquals(3, (int) get_option($optionKey));
    }

    public function test_sequence_works_when_option_does_not_exist()
    {
        $today = current_time('Ymd');
        $optionKey = 'quick_order_daily_seq_'.$today;

        // Ensure option does not exist
        delete_option($optionKey);

        $order = $this->service->createOrder(100);

        $this->assertStringEndsWith('-001', $order->get_meta('_order_number'));

        wp_cache_delete($optionKey, 'options');
        $this->assertEquals(1, (int) get_option($optionKey));
    }

    public function test_stale_daily_sequence_options_are_cleaned_up()
    {
        // Insert stale options for past dates
        update_option('quick_order_daily_seq_20250101', 5, false);
        update_option('quick_order_daily_seq_20250102', 3, false);

        // Force cleanup by creating orders until it triggers (probabilistic)
        // Use reflection to call cleanup directly for deterministic testing
        $reflection = new \ReflectionMethod($this->service, 'cleanupStaleSequenceOptions');
        $reflection->setAccessible(true);
        $reflection->invoke($this->service, current_time('Ymd'));

        $this->assertFalse(get_option('quick_order_daily_seq_20250101'));
        $this->assertFalse(get_option('quick_order_daily_seq_20250102'));
    }
}
