# RazCrypto PHP SDK

Accept cryptocurrency payments (USDT, USDC, DAI) on BSC and Ethereum chains with ease using the official RazCrypto PHP SDK.

## Installation

Install the SDK via Composer:

```bash
composer require worldxgrowth/razcrypto-php-sdk
Quick Start
1. Initialize the API
Get your Gateway ID and Secret Key from the RazCrypto Dashboard.

PHP
require 'vendor/autoload.php';
use RazCrypto\Api;

$api = new Api("YOUR_GATEWAY_ID", "YOUR_SECRET_KEY");
2. Create a Payment
Create a payment to get a checkout URL. You can pass your website's Order ID in the custom_data parameter to link the payment.

PHP
try {
    $payment = $api->payment->create([
        "amount" => 10.50,
        "currency" => "USDT",
        "chain" => "BSC",
        "email" => "customer@example.com",
        "callback_url" => "[https://yourwebsite.com/webhook](https://yourwebsite.com/webhook)",
        "custom_data" => [
            "order_id" => "ORD-12345" // Link your website's order
        ]
    ]);

    // Redirect user to the checkout page OR use RazCrypto JS SDK to open in a modal
    $checkoutUrl = $payment['checkout_page'];
    header("Location: " . $checkoutUrl);
    exit;

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
3. Verify Webhook & Complete Order
When a payment is successful, RazCrypto sends a webhook to your callback_url. Always verify the signature to ensure security.

PHP
use RazCrypto\Webhook;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZCRYPTO_SIGNATURE'] ?? '';
$secretKey = "YOUR_SECRET_KEY";

if (Webhook::verifySignature($payload, $signature, $secretKey)) {
    $data = json_decode($payload, true);
    
    if ($data['event'] === 'payment.completed') {
        $paymentId = $data['payment_id'];
        $orderId = $data['custom_data']['order_id'] ?? null;
        
        // TODO: Mark order as "Paid" in your database using $orderId
    }
    
    http_response_code(200);
    echo json_encode(["status" => "ok"]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Invalid Signature"]);
}
4. Fetch Payment Status Manually (Optional)
PHP
$status = $api->payment->fetch("payid_xxxxxxxxxx");
echo $status['status']; // Returns: pending, completed, or expired
Features
Non-Custodial: Funds go directly to your wallet.

Multi-Chain: Supports Binance Smart Chain (BSC) and Ethereum (ETH).

Secure: HMAC-SHA256 signature verification built-in.

Developer Friendly: Clean, Razorpay-like syntax.
