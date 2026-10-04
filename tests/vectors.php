<?php

// Tiny dependency-free runner for ../../test-vectors.json. Usage: php tests/vectors.php
// (PHPUnit replaces this once the client lands; the vectors stay the same.)

declare(strict_types=1);

use RazCrypto\CheckoutSignature;
use RazCrypto\Exception\SignatureVerificationError;
use RazCrypto\Webhook;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'RazCrypto\\')) {
        require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen('RazCrypto\\'))) . '.php';
    }
});

// Monorepo: sdks/test-vectors.json; standalone package repo: tests/test-vectors.json.
$file = is_file(__DIR__ . '/test-vectors.json') ? __DIR__ . '/test-vectors.json' : __DIR__ . '/../../test-vectors.json';
$vectors = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

$pass = 0;
$fail = 0;

/** @return array{valid: bool, error_code?: string} */
function capture(callable $fn): array
{
    try {
        $fn();
        return ['valid' => true];
    } catch (SignatureVerificationError $e) {
        return ['valid' => false, 'error_code' => $e->getErrorCode()];
    }
}

function check(string $name, array $got, array $expected, int &$pass, int &$fail): void
{
    if ($got == $expected) {
        $pass++;
        echo "ok   $name\n";
    } else {
        $fail++;
        echo "FAIL $name: expected " . json_encode($expected) . ', got ' . json_encode($got) . "\n";
    }
}

foreach ($vectors['webhook'] as $v) {
    $got = capture(static fn () => Webhook::verify($v['raw_body'], $v['headers'], $v['secret'], $v['tolerance_sec'], $v['now']));
    check("webhook/{$v['name']}", $got, $v['expected'], $pass, $fail);

    // Same bytes via raw_body_base64; Symfony/Laravel-style array header values.
    $arrayHeaders = array_map(static fn ($h) => [$h], $v['headers']);
    $got = capture(static fn () => Webhook::verify(base64_decode($v['raw_body_base64'], true), $arrayHeaders, $v['secret'], $v['tolerance_sec'], $v['now']));
    check("webhook/{$v['name']} (bytes, array headers)", $got, $v['expected'], $pass, $fail);
}

$unicode = array_values(array_filter($vectors['webhook'], static fn ($v) => $v['name'] === 'valid_unicode_whitespace_body'))[0];
$event = Webhook::verify($unicode['raw_body'], $unicode['headers'], $unicode['secret'], 300, $unicode['now']);
check('webhook/returns parsed envelope', ['type' => $event['type']], ['type' => 'payment.paid'], $pass, $fail);

foreach ($vectors['checkout'] as $v) {
    $params = ['payment_id' => $v['payment_id'], 'reference_id' => $v['reference_id'], 'status' => $v['status'], 'signature' => $v['signature']];
    check("checkout/{$v['name']}", capture(static fn () => CheckoutSignature::verify($params, $v['secret_key'])), $v['expected'], $pass, $fail);
    check("checkout/{$v['name']} (isValid)", ['valid' => CheckoutSignature::isValid($params, $v['secret_key'])], ['valid' => $v['expected']['valid']], $pass, $fail);
}

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
