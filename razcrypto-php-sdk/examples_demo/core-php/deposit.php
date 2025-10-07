<?php
require __DIR__.'/../vendor/autoload.php';

use RazCrypto\Client;
use RazCrypto\PaymentPage;
use RazCrypto\Exceptions\RazCryptoException;

// Load .env in your app (example only):
// putenv('RAZ_GATEWAY_ID=UID123456');
// putenv('RAZ_SECRET_KEY=rz_sec_xxxxx');
// putenv('RAZ_REDIRECT=true');

$amount   = (float)($_POST['amount'] ?? 0);
$email    = $_POST['email']    ?? '';
$username = $_POST['username'] ?? '';
$mobile   = $_POST['mobile']   ?? null;

try {
    $raz = new Client(getenv('RAZ_GATEWAY_ID'), getenv('RAZ_SECRET_KEY'));

    $payment = $raz->createPayment($amount, [
        'callback_url'    => 'https://yourdomain.com/webhook.php',
        'email'           => $email,
        'username'        => $username,
        'mobile'          => $mobile,
        'custom_data'     => [
            'order_id' => 'ORD-' . time(),
            'meta'     => ['env' => 'core-php-demo']
        ],
        'subscription_id' => 'SUB_DEMO_1'
    ]);

} catch (RazCryptoException $e) {
    http_response_code(400);
    echo 'Payment error: ' . htmlspecialchars($e->getMessage());
    exit;
}

if (($payment['status'] ?? '') !== 'success') {
    http_response_code(400);
    echo 'Payment failed: ' . htmlspecialchars($payment['message'] ?? 'Unknown');
    exit;
}

// Hosted vs Custom
$redirect = (getenv('RAZ_REDIRECT') ?: 'true') === 'true';

if ($redirect && !empty($payment['payment_url'])) {
    header('Location: ' . $payment['payment_url']);
    exit;
}

// Render in-app page (QR + address + amount + copy)
PaymentPage::render($payment, [
    'logo_url'      => getenv('RAZ_LOGO_URL') ?: '',
    'primary_color' => getenv('RAZ_PRIMARY_COLOR') ?: '#4f46e5',
]);
