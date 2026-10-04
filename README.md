# RazCrypto Gateway — PHP SDK

Official PHP client for [RazCrypto Gateway](https://razcryptogateway.com), the non-custodial crypto payment gateway.

- **Composer:** `worldxgrowth/razcrypto-php-sdk`
- **Requires:** PHP ≥ 8.1, cURL
- **Dependencies:** no other Composer packages
- **License:** MIT

```bash
composer require worldxgrowth/razcrypto-php-sdk
```


## Upgrading

This major version is the SDK for the new RazCrypto Gateway: initialise with only your secret key (Dashboard → API apps) and use the new `/v1` API. Pin the previous major version only if you still use the old gateway.

## Create a payment

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

## API

| Area | Methods |
|---|---|
| Payments | `$rc->payments()->create($params, $idempotencyKey)`, `retrieve($id)`, `all($params)`, `methods($id)`, `quote($id, $chain, $coin)`, `cancel($id)`, `simulate($id, $outcome)` (test mode) |
| Payment links | `$rc->paymentLinks()->create(...)`, `retrieve`, `all`, `update`, `deactivate` |
| Others | `$rc->chains()`, `$rc->rates(['USDT','BNB'], 'USD')`, `$rc->merchant()`, `$rc->event($id)` |

**Errors:**
- API errors throw `RazCrypto\Exception\ApiError` with the HTTP status, `code`, `param` and request id.
- Network problems throw `RazCrypto\Exception\ConnectionError`.
- The client retries network errors, `429` and `5xx`.

Options: `new Client($key, ['timeout' => 30, 'max_retries' => 2])`. The client talks to `https://razcryptogateway.com`
by default; `api_base` (or env `RAZCRYPTO_API_BASE`) is only for staging or a self-hosted gateway.

## Verify webhooks (Standard Webhooks)

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

`Webhook::verify()` also accepts:
- Laravel/Symfony header bags (`$request->headers->all()`)
- a PSR-7 request

## Verify the checkout signature

```php
use RazCrypto\CheckoutSignature;

// Redirect query: ?payment_id=…&reference_id=…&status=…&signature=…
if (!CheckoutSignature::isValid($_GET, getenv('RAZCRYPTO_WEBHOOK_SECRET'))) {
    // do not trust it — wait for the webhook
}
```

## Test mode
- Use `sk_test_…` keys.
- `$rc->payments()->simulate($id, 'paid')` runs the full flow, webhooks included.

Tests: `php tests/client.php` (no PHPUnit needed). It also runs the shared vectors in `../test-vectors.json`.
WooCommerce users: see [`../woocommerce`](../woocommerce).
