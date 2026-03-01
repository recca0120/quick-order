# WooCommerce Quick Order

![Tests](https://github.com/recca0120/quick-order/actions/workflows/tests.yml/badge.svg)
![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

快速建立指定金額的 WooCommerce 訂單，並產生付款連結。

## 功能特色

- 後台管理介面快速建立訂單（金額、商品名稱、備註）
- 客戶資訊收集（Email、姓名、電話、地址）並寫入帳單欄位
- Email 已存在自動關聯既有帳號；不存在可自動建立帳號（可於設定控制）
- 自動產生付款連結，支援一鍵複製
- REST API 支援外部系統整合
- Shortcode `[quick_order]` 可嵌入任意頁面
- API Key 驗證（支援 `wp-config.php` 常數或後台設定）

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

- **建立訂單** — 輸入客戶資料（Email、姓名、電話、地址）、金額、商品名稱、備註，送出後取得付款連結
- **設定** — 設定 API Key（用於 REST API 驗證）、自動建立帳號開關

### Shortcode

在任意頁面或文章中使用：

```
[quick_order]
```

需具備 `manage_woocommerce` 權限才會顯示表單。

### REST API

所有端點需透過 API Key 驗證，在 Header 加上 `X-API-Key`。

#### 建立訂單

```
POST /wp-json/quick-order/v1/orders
```

| 參數 | 類型 | 必填 | 說明 |
|------|------|:----:|------|
| `amount` | number | ✓ | 訂單金額 |
| `name` | string | | 商品名稱 |
| `note` | string | | 備註 |
| `email` | string | | 客戶 Email |
| `first_name` | string | | 名字 |
| `last_name` | string | | 姓氏 |
| `phone` | string | | 電話 |
| `address_1` | string | | 地址 |
| `city` | string | | 城市 |
| `postcode` | string | | 郵遞區號 |

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
PUT /wp-json/quick-order/v1/orders/{id}/status
```

| 參數 | 類型 | 必填 | 說明 |
|------|------|:----:|------|
| `status` | string | ✓ | 訂單狀態 |
| `note` | string | | 狀態變更備註 |

## 客戶帳號設定

當訂單帶有 Email 時：

- **Email 已存在** → 自動關聯既有 WordPress/WooCommerce 帳號
- **Email 不存在 + 自動建立帳號開啟** → 透過 `wc_create_new_customer()` 建立新帳號並關聯
- **Email 不存在 + 自動建立帳號關閉** → 僅填入帳單資訊，不建立帳號（guest order）
- **無 Email** → guest order，行為不變

自動建立帳號可在 **WooCommerce → Quick Order → 設定** 中控制（預設開啟）。

## API Key 設定

支援兩種模式：

**1. 透過 `wp-config.php` 常數（推薦）：**

```php
define('QUICK_ORDER_API_KEY', 'your-api-key');
```

設定後後台欄位會自動停用並顯示遮罩。

**2. 透過後台設定：**

在 WooCommerce → Quick Order → 設定 中輸入 API Key。

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
