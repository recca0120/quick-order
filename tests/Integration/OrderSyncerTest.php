<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\OrderService;
use Recca0120\QuickOrder\OrderSyncer;
use WP_UnitTestCase;

class OrderSyncerTest extends WP_UnitTestCase
{
    private $paymentData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentData = require dirname(__DIR__).'/fixtures/payment_data.php';
    }

    // ── Header 解析 ────────────────────────────────────────────

    public function test_parse_header_returns_array_for_valid_base64_json()
    {
        $syncer = $this->makeSyncer();
        $encoded = $this->makeHeaderValue($this->paymentData);

        $result = $syncer->parseHeader($encoded);

        $this->assertIsArray($result);
        $this->assertEquals('QO-20260307-001', $result['transaction_id']);
    }

    public function test_parse_header_returns_null_for_empty_value()
    {
        $this->assertNull($this->makeSyncer()->parseHeader(''));
    }

    public function test_parse_header_returns_null_for_invalid_base64()
    {
        $this->assertNull($this->makeSyncer()->parseHeader('not-valid-base64!!!'));
    }

    public function test_parse_header_returns_null_for_non_json_content()
    {
        $this->assertNull($this->makeSyncer()->parseHeader(base64_encode('not json')));
    }

    // ── 建立訂單 (Integration) ───────────────────────────────

    public function test_creates_wc_order_with_correct_amount_and_billing()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $this->assertEquals(1000.0, (float) $order->get_total());
        $this->assertEquals('wang@example.com', $order->get_billing_email());
        $this->assertEquals('王', $order->get_billing_first_name());
        $this->assertEquals('小明', $order->get_billing_last_name());
        $this->assertEquals('0912345678', $order->get_billing_phone());
    }

    public function test_uses_description_as_order_item_name()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $items = $order->get_items('fee');
        $item = reset($items);
        $this->assertEquals('測試商品', $item->get_name());
    }

    public function test_uses_transaction_id_as_order_number()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $this->assertEquals('QO-20260307-001', $order->get_meta('_order_number'));
    }

    public function test_uses_email_local_part_when_name_is_empty()
    {
        $data = array_merge($this->paymentData, [
            'transaction_id' => 'QO-20260307-002',
            'name' => '',
            'email' => 'john.doe@example.com',
        ]);
        $order = $this->runWithResponseHeader($data);

        $this->assertEquals('john.doe', $order->get_billing_first_name());
    }

    // ── Transaction meta ──────────────────────────────────────

    public function test_stores_transaction_reference_as_wc_transaction_id()
    {
        $data = array_merge($this->paymentData, ['transaction_reference' => 'GW-REF-001']);
        $order = $this->runWithResponseHeader($data);

        $this->assertEquals('GW-REF-001', $order->get_transaction_id());
    }

    public function test_stores_extra_fields_as_payment_meta()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $this->assertEquals('001', $order->get_meta('_payment_bank_code'));
        $this->assertEquals('12345678901', $order->get_meta('_payment_account_number'));
    }

    // ── Gateway ID + Status 映射 ─────────────────────────────

    public function test_resolves_gateway_id_from_gateway_name_and_payment_method()
    {
        $syncer = $this->makeSyncer();

        $this->assertEquals('omnipay_newebpay_atm', $syncer->resolveGatewayId('newebpay', 'atm'));
        $this->assertEquals('omnipay_yipay_cvs', $syncer->resolveGatewayId('yipay', 'cvs'));
        $this->assertEquals('omnipay_banktransfer', $syncer->resolveGatewayId('bank-transfer', 'atm'));
    }

    public function test_sets_payment_method_and_title_on_order()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $this->assertEquals('omnipay_newebpay_atm', $order->get_payment_method());
        $this->assertEquals('newebpay', $order->get_payment_method_title());
    }

    public function test_maps_status_new_to_pending()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $this->assertEquals('pending', $order->get_status());
    }

    public function test_maps_status_completed_to_completed()
    {
        $data = array_merge($this->paymentData, [
            'transaction_id' => 'QO-20260307-002',
            'status' => 'completed',
        ]);
        $order = $this->runWithResponseHeader($data);

        $this->assertEquals('completed', $order->get_status());
    }

    // ── 時間欄位 ─────────────────────────────────────────────

    public function test_sets_date_created_from_created_at()
    {
        $data = array_merge($this->paymentData, [
            'created_at' => '2026-03-07T20:28:15.000000Z',
        ]);
        $order = $this->runWithResponseHeader($data);

        $this->assertEquals('2026-03-07 20:28:15', $order->get_date_created()->date('Y-m-d H:i:s'));
    }

    public function test_sets_date_paid_from_completed_at()
    {
        $data = array_merge($this->paymentData, [
            'completed_at' => '2026-03-07T20:30:00.000000Z',
            'status' => 'completed',
        ]);
        $order = $this->runWithResponseHeader($data);

        $this->assertEquals('2026-03-07 20:30:00', $order->get_date_paid()->date('Y-m-d H:i:s'));
    }

    public function test_does_not_set_date_paid_when_completed_at_is_null()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $this->assertNull($order->get_date_paid());
    }

    // ── 重複訂單（以 transaction_id 判斷）─────────────────────

    public function test_does_not_create_duplicate_order_for_same_transaction_id()
    {
        $this->runWithResponseHeader($this->paymentData);
        $this->runWithResponseHeader($this->paymentData);

        $orders = wc_get_orders(['limit' => -1, 'meta_key' => '_order_number', 'meta_value' => 'QO-20260307-001']);
        $this->assertCount(1, $orders);
    }

    public function test_returns_existing_order_on_duplicate_notification()
    {
        $first = $this->runWithResponseHeader($this->paymentData);
        $second = $this->runWithResponseHeader($this->paymentData);

        $this->assertEquals($first->get_id(), $second->get_id());
    }

    public function test_updates_status_when_same_transaction_id_sent_with_new_status()
    {
        $this->runWithResponseHeader($this->paymentData); // status: new → pending

        $completed = array_merge($this->paymentData, ['status' => 'completed', 'transaction_reference' => 'GW-REF-001']);
        $order = $this->runWithResponseHeader($completed);

        $this->assertEquals('completed', $order->get_status());
        $this->assertEquals('GW-REF-001', $order->get_transaction_id());
    }

    public function test_updates_extra_fields_on_existing_order()
    {
        $this->runWithResponseHeader($this->paymentData);

        $updated = array_merge($this->paymentData, ['bank_code' => '808', 'account_number' => '999999999']);
        $order = $this->runWithResponseHeader($updated);

        $this->assertEquals('808', $order->get_meta('_payment_bank_code'));
        $this->assertEquals('999999999', $order->get_meta('_payment_account_number'));
    }

    public function test_recreates_order_when_previous_order_was_trashed()
    {
        $first = $this->runWithResponseHeader($this->paymentData);
        $firstId = $first->get_id();

        $first->delete(false);
        $this->assertEquals('trash', get_post_status($firstId));

        $second = $this->runWithResponseHeader($this->paymentData);

        $this->assertNotEquals($firstId, $second->get_id());
        $this->assertEquals('QO-20260307-001', $second->get_meta('_order_number'));
    }

    public function test_recreates_order_when_previous_order_was_deleted()
    {
        $first = $this->runWithResponseHeader($this->paymentData);
        $firstId = $first->get_id();

        $first->delete(true);
        $this->assertFalse(wc_get_order($firstId));

        $second = $this->runWithResponseHeader($this->paymentData);

        $this->assertNotEquals($firstId, $second->get_id());
        $this->assertEquals('QO-20260307-001', $second->get_meta('_order_number'));
    }

    // ── sync ─────────────────────────────────────────────

    public function test_sync_from_data_creates_order_with_full_customer_fields()
    {
        $syncer = $this->makeSyncer();
        $order = $syncer->sync([
            'transaction_id' => 'QO-20260309-001',
            'amount' => 500,
            'description' => '直接同步',
            'note' => '備註測試',
            'name' => '李大華',
            'email' => 'lee@example.com',
            'phone_number' => '0933333333',
            'address_1' => '中山北路',
            'city' => '台北市',
            'postcode' => '104',
            'gateway_name' => 'newebpay',
            'payment_method' => 'atm',
        ]);

        $this->assertEquals(500.0, (float) $order->get_total());
        $this->assertEquals('lee@example.com', $order->get_billing_email());
        $this->assertEquals('0933333333', $order->get_billing_phone());
        $this->assertEquals('中山北路', $order->get_billing_address_1());
        $this->assertEquals('台北市', $order->get_billing_city());
        $this->assertEquals('104', $order->get_billing_postcode());
        $this->assertEquals('omnipay_newebpay_atm', $order->get_payment_method());
    }

    // ── ATM 後 5 碼 ────────────────────────────────────────────

    public function test_atm_sync_stores_last5_digits_of_account_number()
    {
        $order = $this->runWithResponseHeader(array_merge($this->paymentData, [
            'payment_method' => 'atm',
            'account_number' => '12345678901',
        ]));

        $this->assertEquals('78901', $order->get_meta('_omnipay_remittance_last5'));
    }

    public function test_atm_sync_truncates_from_full_16_digit_account()
    {
        $order = $this->runWithResponseHeader(array_merge($this->paymentData, [
            'payment_method' => 'atm',
            'account_number' => '1234567890123456',
        ]));

        $this->assertEquals('23456', $order->get_meta('_omnipay_remittance_last5'));
    }

    public function test_non_atm_sync_does_not_store_remittance_last5()
    {
        $order = $this->runWithResponseHeader(array_merge($this->paymentData, [
            'payment_method' => 'cvs',
            'account_number' => '12345678901',
        ]));

        $this->assertEmpty($order->get_meta('_omnipay_remittance_last5'));
    }

    public function test_atm_sync_without_account_number_does_not_store_remittance_last5()
    {
        $data = $this->paymentData;
        unset($data['account_number']);

        $order = $this->runWithResponseHeader(array_merge($data, [
            'payment_method' => 'atm',
        ]));

        $this->assertEmpty($order->get_meta('_omnipay_remittance_last5'));
    }

    // ── created_via ────────────────────────────────────────────

    public function test_sync_sets_created_via_to_checkout_by_default()
    {
        $order = $this->runWithResponseHeader($this->paymentData);

        $this->assertEquals('checkout', $order->get_created_via());
    }

    public function test_sync_uses_created_via_from_data()
    {
        $data = array_merge($this->paymentData, [
            'transaction_id' => 'QO-20260307-003',
            'created_via' => 'rest-api',
        ]);
        $order = $this->runWithResponseHeader($data);

        $this->assertEquals('rest-api', $order->get_created_via());
    }

    // ── 空值不建立訂單 ──────────────────────────────────────────

    public function test_does_not_create_order_when_header_is_empty()
    {
        $syncer = $this->makeSyncer();
        $result = $syncer->syncFromBase64('');

        $this->assertNull($result);
        $orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        $this->assertEmpty($orders);
    }

    private function makeHeaderValue(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    private function makeSyncer(): OrderSyncer
    {
        return new OrderSyncer(new OrderService());
    }

    private function runWithResponseHeader(array $data): \WC_Order
    {
        $syncer = $this->makeSyncer();

        return $syncer->syncFromBase64($this->makeHeaderValue($data));
    }
}
