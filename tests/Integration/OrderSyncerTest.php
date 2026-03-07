<?php

namespace Recca0120\QuickOrder\Tests\Integration;

use Recca0120\QuickOrder\OrderService;
use Recca0120\QuickOrder\OrderSyncer;
use WP_UnitTestCase;

/**
 * @group reverse-proxy
 */
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

    // ── 空值不建立訂單 ──────────────────────────────────────────

    public function test_does_not_create_order_when_header_is_empty()
    {
        $syncer = $this->makeSyncer();
        $syncer->sync('');

        $this->assertNull($syncer->getLastOrder());
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
        $syncer->sync($this->makeHeaderValue($data));

        return $syncer->getLastOrder();
    }
}
