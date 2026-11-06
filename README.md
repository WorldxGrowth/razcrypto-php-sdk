<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:0B1F3A,50:1565C0,100:29B6F6&height=160&section=header&text=RazCrypto%20PHP%20SDK&fontSize=38&fontColor=ffffff&fontAlignY=38&desc=by%20RazCrypto%20Gateway&descAlignY=60&descSize=16" width="100%" alt="RazCrypto%20PHP%20SDK" />

**Accept USDT & crypto payments in Laravel and Core PHP — the official PHP client for [RazCrypto Gateway](https://razcryptogateway.com).**

<img src="https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.1+" />
<img src="https://img.shields.io/badge/Laravel-ready-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel" />
<img src="https://img.shields.io/badge/dependencies-none-2EA043?style=flat-square" alt="No dependencies" />
<img src="https://img.shields.io/badge/USDT-BEP--20-26A17B?style=flat-square&logo=tether&logoColor=white" alt="USDT BEP-20" />
<img src="https://img.shields.io/badge/license-MIT-blue?style=flat-square" alt="MIT" />

<p>
<a href="#-installation">Install</a> •
<a href="#-create-a-payment">Create payment</a> •
<a href="#-api-reference">API</a> •
<a href="#-verify-webhooks">Webhooks</a> •
<a href="#-test-mode">Test mode</a>
</p>

</div>

---

## ✨ Features

- 🔌 **Plug & play** — works with Laravel, Symfony and plain PHP
- 📦 **Zero dependencies** — only PHP ≥ 8.1 and cURL
- 🔁 **Safe retries** — idempotency keys + automatic retry on network errors, `429` and `5xx`
- 🔐 **Webhook verification** — Standard Webhooks signatures, PSR-7 and Laravel header bags supported
- 🧪 **Test mode** — simulate a full payment, webhooks included

## 📥 Installation

```bash
composer require worldxgrowth/razcrypto-php-sdk
```

| | |
|---|---|
| **Composer** | `worldxgrowth/razcrypto-php-sdk` |
| **Requires** | PHP ≥ 8.1, cURL |
| **License** | MIT |

> **Upgrading?** This major version is for the new RazCrypto Gateway: initialise with only your secret key (Dashboard → API apps) and use the new `/v1` API. Pin the previous major version only if you still use the old gateway.

## 🔄 How it works

1. **Your server** creates a payment with your secret key (`sk_…`).
2. **The customer** pays on the RazCrypto checkout (QR code or wallet link).
3. **RazCrypto** sends a signed `payment.paid` webhook to your server.
4. **You verify** the webhook and mark the order as paid.

> 💡 **Trust the webhook, not the browser.** The redirect and `onSuccess` are only for a fast "Paid" screen.

## 🚀 Create a payment

```php
<?php
require 'vendor/autoload.php';

$rc = new \RazCrypto\Client(getenv('RAZCRYPTO_SECRET_KEY')); // sk_test_… or sk_live_…

$payment = $rc->payments()->create([
    'amount'       => '49.00',
    'currency'     => 'USD',
    'reference_id' => $order->id,
    'redirect_url' => 'https://shop.example/thanks',
], $order->id); // idempotency key — safe to retry

header('Location: ' . $payment['checkout_url']);
```

## 📚 API reference

**Payments**
- `$rc->payments()->create($params, $idempotencyKey)`
- `retrieve($id)` · `all($params)` · `cancel($id)`
- `methods($id)` · `quote($id, $chain, $coin)`
- `simulate($id, $outcome)` — test mode only

**Payment links**
- `$rc->paymentLinks()->create(...)` · `retrieve` · `all` · `update` · `deactivate`

**Others**
- `$rc->chains()` · `$rc->rates(['USDT','BNB'], 'USD')` · `$rc->merchant()` · `$rc->event($id)`

**Errors**
- API errors throw `RazCrypto\Exception\ApiError` with the HTTP status, `code`, `param` and request id.
- Network problems throw `RazCrypto\Exception\ConnectionError`.

**Options**

```php
new Client($key, ['timeout' => 30, 'max_retries' => 2]);
```

The client talks to `https://razcryptogateway.com` by default. `api_base` (or env `RAZCRYPTO_API_BASE`) is only for staging or a self-hosted gateway.

## 🔐 Verify webhooks

Standard Webhooks signatures:

```php
use RazCrypto\Webhook;
use RazCrypto\Exception\SignatureVerificationError;

$raw = file_get_contents('php://input');
try {
    $event = Webhook::verify($raw, getallheaders(), getenv('RAZCRYPTO_WEBHOOK_SECRET')); // whsec_…
} catch (SignatureVerificationError $e) {
    http_response_code(400);
    exit;
}
if ($event['type'] === 'payment.paid') {
    markOrderPaid($event['data']['reference_id']);
}
http_response_code(200);
```

`Webhook::verify()` also accepts Laravel/Symfony header bags (`$request->headers->all()`) and PSR-7 requests.

## ✅ Verify the checkout signature

```php
use RazCrypto\CheckoutSignature;

// Redirect query: ?payment_id=…&reference_id=…&status=…&signature=…
if (!CheckoutSignature::isValid($_GET, getenv('RAZCRYPTO_WEBHOOK_SECRET'))) {
    // do not trust it — wait for the webhook
}
```

## 🧪 Test mode

- Use `sk_test_…` keys.
- `$rc->payments()->simulate($id, 'paid')` runs the full flow, webhooks included.

```bash
php tests/client.php   # no PHPUnit needed
```

> 🛒 **WooCommerce store?** A RazCrypto WooCommerce plugin is available — ask us at worldxgrowth@gmail.com.

---

## 🧩 Other RazCrypto SDKs

- [🟢 Node.js — Express / Next.js](https://github.com/WorldxGrowth/razcrypto-node-sdk)
- [🐍 Python — Django / Flask / FastAPI](https://github.com/WorldxGrowth/razcrypto-python-sdk)
- [🌐 Checkout JS — any website](https://github.com/WorldxGrowth/razcrypto-checkout-js)
- [💙 Flutter — Android & iOS](https://github.com/WorldxGrowth/razcrypto-flutter-sdk)
- [⚛️ React Native — Android & iOS](https://github.com/WorldxGrowth/razcrypto-react-native-sdk)

---

<div align="center">

**Built and maintained by [Worldxgrowth](https://worldxgrowth.com)** — Software Development Agency

<a href="https://razcryptogateway.com"><img src="https://img.shields.io/badge/Get%20API%20keys-razcryptogateway.com-29B6F6?style=for-the-badge" alt="Get API keys" /></a>
<a href="mailto:worldxgrowth@gmail.com"><img src="https://img.shields.io/badge/Support-worldxgrowth@gmail.com-D14836?style=for-the-badge&logo=gmail&logoColor=white" alt="Support" /></a>

<sub>Released under the MIT License.</sub>

</div>
