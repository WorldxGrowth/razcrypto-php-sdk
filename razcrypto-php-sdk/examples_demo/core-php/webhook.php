<?php
require __DIR__.'/../vendor/autoload.php';

use RazCrypto\Client;

// 1) Raw body
$raw = file_get_contents('php://input');

// 2) Signature from header
$received = $_SERVER['HTTP_X_RAZCRYPTO_SIGNATURE'] ?? '';

// 3) Verify signature
if (!Client::verifyWebhook($raw, $received, getenv('RAZ_SECRET_KEY'))) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// 4) Parse JSON
$data = json_decode($raw, true);
$event = $data['event'] ?? '';

if ($event === 'payment.completed') {
    $paymentId = $data['payment_id'] ?? '';
    $amount    = (float)($data['amount'] ?? 0);
    $txHash    = $data['tx_hash'] ?? '';

    // TODO: idempotent credit (check if this payment already processed)
    // e.g., check DB by $paymentId or $txHash; if processed => exit 200.
    // creditWallet($data['username'], $amount);
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
