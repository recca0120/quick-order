# WooCommerce Quick Order

![Tests](https://github.com/recca0120/quick-order/actions/workflows/tests.yml/badge.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

快速建立指定金額的 WooCommerce 訂單，並產生付款連結。

## 功能特色

- 後台管理介面快速建立訂單（金額、商品名稱、備註、客戶資訊）
- 自訂訂單編號（自動產生 `{前綴}-{日期}-{流水號}` 或手動填入）
- 客戶資訊收集（Email、姓名、電話、地址）並寫入帳單欄位
- Email 已存在自動關聯既有帳號；不存在可設定是否自動建立帳號（預設關閉）
- 自動產生付款連結，支援一鍵複製
- REST API 支援外部系統整合（建單、同步、查詢、狀態更新）
- Shortcode `[quick_order]` 可嵌入任意頁面
- API Key 驗證（支援後台設定或 `add_filter` 覆寫）
- OrderSyncer 支援外部金流回調同步訂單（create-or-update）
- 後台工具：補同步客戶關聯（將 guest 訂單補關聯到對應帳號）
- 序號自動產生（SHA-256，`transaction_id + salt`），設定 salt 即啟用，顯示於 Email、前台及後台訂單詳情頁
- ATM 付款時自動擷取匯款帳號後五碼（`account_number` 欄位）
- 客戶 IP 記錄支援（`customer_ip` 欄位）
- 訂單來源（`created_via`）預設為 `checkout`，可透過 filter 或 API 參數覆寫
- 訂單歸因（Order Attribution）預設為「直接」，可透過 filter 自訂
- Filter 覆寫設定時自動隱藏後台對應欄位與 section

## 系統需求

- PHP 7.2+
- WordPress 5.0+
- WooCommerce

## 安裝

### 從 GitHub Releases 下載

1. 前往 [Releases 頁面](https://github.com/recca0120/quick-order/releases)
2. 下載 **`quick-order.zip`**
3. 在 WordPress 後台 → 外掛 → 安裝外掛 → 上傳外掛
4. 上傳 zip 檔案並啟用

## 使用方式

### 後台管理

啟用外掛後，前往 **WooCommerce → Quick Order**：

- **建立訂單** — 輸入訂單編號（選填）、客戶資料（Email、姓名、電話、地址）、金額、商品名稱、備註，送出後取得付款連結
- **工具** — 補同步客戶關聯：輸入客戶 Email，將該 Email 的 guest 訂單補關聯到對應帳號
- **設定** — 設定 API Key、自動建立帳號開關、自訂訂單編號顯示與前綴、序號 Salt（填入即啟用序號功能）

### Shortcode

在任意頁面或文章中使用：

```
[quick_order]
```

需具備 `manage_woocommerce` 權限才會顯示表單。

### REST API

所有端點需透過以下任一方式驗證：
- Header 加上 `X-API-Key: {your-api-key}`
- Header 加上 `Authorization: Bearer {your-api-key}`

#### 建立訂單

```
POST /wp-json/quick-order/v1/orders
```

| 參數 | 類型 | 必填 | 說明 |
|------|------|:----:|------|
| `amount` | number | ✓ | 訂單金額 |
| `description` | string | | 商品名稱 |
| `note` | string | | 備註 |
| `order_number` | string | | 自訂訂單編號（未填則自動產生） |
| `name` | string | | 客戶姓名 |
| `email` | string | | 客戶 Email |
| `phone_number` | string | | 電話 |
| `address_1` | string | | 地址 |
| `city` | string | | 城市 |
| `postcode` | string | | 郵遞區號 |
| `transaction_reference` | string | | 金流商交易編號 |
| `gateway_name` | string | | 金流名稱（如 `newebpay`） |
| `payment_method` | string | | 付款方式（如 `atm`、`cvs`） |
| `created_at` | string | | 訂單建立時間（ISO 8601） |
| `completed_at` | string | | 付款完成時間（ISO 8601） |
| `customer_ip` | string | | 客戶 IP 位址（未填則不記錄） |
| `created_via` | string | | 訂單來源（未填則套用 filter，預設 `checkout`） |

#### 同步訂單（create-or-update）

```
POST /wp-json/quick-order/v1/orders/sync
```

接受與建立訂單相同的欄位，另外以 `transaction_id` 作為冪等鍵：
- `transaction_id` 不存在 → 建立新訂單
- `transaction_id` 已存在 → 更新既有訂單（狀態、付款資訊）

| 參數 | 說明 |
|------|------|
| `transaction_id` | 訂單唯一識別碼（對應 `_order_number`） |
| `status` | 狀態（`new` → `pending`，其餘直接對應） |
| 其他欄位 | 同建立訂單 |

#### 查詢訂單

```
GET /wp-json/quick-order/v1/orders/{id}
```

#### 訂單列表

```
GET /wp-json/quick-order/v1/orders?status=pending
```

#### 更新訂單狀態

```
PUT /wp-json/quick-order/v1/orders/{transaction_id}/status
```

以 `transaction_id`（即 `_order_number`）識別訂單，不需要 WooCommerce 內部 ID。

| 參數 | 類型 | 必填 | 說明 |
|------|------|:----:|------|
| `status` | string | ✓ | 訂單狀態 |
| `note` | string | | 狀態變更備註 |

#### 補同步客戶關聯

```
POST /wp-json/quick-order/v1/customers/link-orders
```

將指定 Email 的 guest 訂單（`customer_id = 0`）補關聯到對應的 WordPress 帳號。

| 參數 | 類型 | 必填 | 說明 |
|------|------|:----:|------|
| `email` | string | ✓ | 客戶 Email |

回應：`{ "linked": 2 }`（已關聯的訂單數）

### OrderSyncer

`OrderSyncer` 負責接收外部金流回調資料並同步到 WooCommerce 訂單。無外部依賴（不需要 PSR-7），可在任何環境中使用。

#### 基本用法

```php
use Recca0120\QuickOrder\OrderSyncer;

$syncer = new OrderSyncer();

// 傳入 base64 編碼的 JSON 字串，回傳同步後的訂單（或 null）
$order = $syncer->syncFromBase64($base64EncodedJson);

// 直接傳入資料陣列
$order = $syncer->sync($data);
```

#### 資料格式

傳入 `sync()` 的陣列結構：

| 欄位 | 類型 | 說明 |
|------|------|------|
| `transaction_id` | string | 訂單唯一識別碼（對應 `_order_number`，用於 create-or-update） |
| `transaction_reference` | string | 金流商回傳的交易編號 |
| `gateway_name` | string | 金流名稱（如 `newebpay`） |
| `payment_method` | string | 付款方式（如 `atm`、`cvs`） |
| `amount` | number | 金額 |
| `description` | string | 商品名稱 |
| `note` | string | 備註 |
| `status` | string | 狀態（`new` → `pending`，其餘直接對應） |
| `created_at` | string | 訂單建立時間（ISO 8601） |
| `completed_at` | string | 付款完成時間（ISO 8601） |
| `name` | string | 客戶姓名 |
| `email` | string | 客戶 Email |
| `phone_number` | string | 電話 |
| `address_1` | string | 地址 |
| `city` | string | 城市 |
| `postcode` | string | 郵遞區號 |
| `customer_ip` | string | 客戶 IP 位址（未填則不記錄） |
| `created_via` | string | 訂單來源（未填則套用 filter，預設 `checkout`） |
| 其他欄位 | any | 自動存為 `_payment_{欄位名}` meta |

範例 JSON：

```json
{
    "transaction_id": "QO-20260302-001",
    "transaction_reference": "GW-REF-001",
    "gateway_name": "newebpay",
    "payment_method": "atm",
    "amount": 1000,
    "description": "測試商品",
    "status": "completed",
    "created_at": "2026-03-02T10:00:00.000000Z",
    "completed_at": "2026-03-02T10:30:00.000000Z",
    "name": "王小明",
    "email": "wang@example.com",
    "phone_number": "0912345678",
    "bank_code": "001",
    "account_number": "12345678901"
}
```

#### 行為說明

- **新訂單**：`transaction_id` 不存在時建立新 WooCommerce 訂單
- **重複通知**：`transaction_id` 已存在時更新既有訂單（狀態、付款資訊）
- **動態欄位**：固定欄位以外的資料（如 `bank_code`、`account_number`）自動存為 `_payment_*` order meta
- **Gateway ID**：由 `gateway_name` + `payment_method` 組合，格式為 `omnipay_{gateway}_{method}`；`bank-transfer` 例外，固定為 `omnipay_banktransfer`

## 自訂訂單編號

每筆 Quick Order 訂單會自動產生格式化編號，例如 `QO-20260302-001`：

- **格式**：`{前綴}-{YYYYMMDD}-{當日流水號}`
- **手動填入**：建立訂單時可自行指定編號，未填則自動產生
- **顯示控制**：可在設定頁開關是否在 WooCommerce 中顯示自訂編號（預設開啟）
- **前綴自訂**：可在設定頁修改前綴（預設 `QO`）

## 客戶帳號設定

當訂單帶有 Email 時：

- **Email 已存在** → 自動關聯既有 WordPress/WooCommerce 帳號
- **Email 不存在 + 自動建立帳號開啟** → 透過 `wc_create_new_customer()` 建立新帳號（以 `phone_number` 為密碼，未提供則隨機產生）
- **Email 不存在 + 自動建立帳號關閉（預設）** → 僅填入帳單資訊，不建立帳號（guest order）
- **無 Email** → guest order，行為不變

> **建議**：保持預設的關閉狀態。客戶自行在前台註冊後，可在後台「工具」頁使用「補同步關聯」功能，或透過 `POST /customers/link-orders` API，將過去的 guest 訂單補關聯到對應帳號。

## 序號

每筆訂單建立時可自動產生一組序號，存於 `_serial_number` order meta。

- **產生方式**：`SHA-256(transaction_id + salt)`，轉大寫十六進制（64 字元）
- **啟用條件**：在設定頁填入「序號 Salt」即自動啟用；留空則不產生
- **顯示位置**：
  - 客戶訂單確認 Email（訂單狀態為 `completed` 時）
  - 前台訂單詳情頁（訂單狀態為 `completed` 時）
  - 後台訂單詳情頁帳單區塊（任何狀態皆顯示）
- **顯示控制**：透過 `quick_order_serial_display` filter 控制（預設顯示）

```php
// 完全停用顯示
add_filter('quick_order_serial_display', '__return_false');

// 依條件控制（例如只有滿千元才顯示）
add_filter('quick_order_serial_display', function (bool $display, \WC_Order $order) {
    return (float) $order->get_total() >= 1000;
}, 10, 2);
```

## Filter 覆寫設定

以下設定值可透過 `add_filter` 程式化覆寫。設定後後台對應欄位會自動隱藏。

### API Key

```php
add_filter('quick_order_api_key', function () {
    return 'your-secret-api-key';
});
```

### 序號 Salt

```php
add_filter('quick_order_serial_salt', function () {
    return 'your-secret-salt';
});
```

### 自動建立帳號

```php
add_filter('quick_order_auto_create_customer', function () {
    return 'yes'; // 或 'no'
});
```

### 訂單來源（created_via）

```php
add_filter('quick_order_created_via', function () {
    return 'my-system';
});
```

### 訂單歸因（Order Attribution）

```php
add_filter('quick_order_order_attribution', function () {
    return [
        'source_type' => 'utm',
        'utm_source'  => 'google',
        'origin'      => 'google.com',
    ];
});

// 停用歸因
add_filter('quick_order_order_attribution', fn () => []);
```

### 序號顯示

```php
// 停用所有序號顯示
add_filter('quick_order_serial_display', '__return_false');
```

建議在 `functions.php` 或 mu-plugin 中設定，確保在外掛載入前生效。

---

## 開發

```bash
# 安裝測試環境
./bin/install-wp-tests.sh

# 安裝依賴
composer install

# 執行測試
composer test

# 執行測試（含覆蓋率）
./vendor/bin/phpunit --coverage-text
```

## 授權

MIT License
