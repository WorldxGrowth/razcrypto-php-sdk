<?php

// Client tests with a fake transport. Optional live smoke: SK=sk_test_… API=http://127.0.0.1:4000 php tests/client.php

declare(strict_types=1);

use RazCrypto\Client;
use RazCrypto\Exception\ApiError;
use RazCrypto\Http\Transport;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'RazCrypto\\')) {
        require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen('RazCrypto\\'))) . '.php';
    }
});

final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $calls = [];

    /** @param list<array{status: int, headers: array<string, string>, body: string}> $responses */
    public function __construct(private array $responses)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? ['status' => 500, 'headers' => [], 'body' => ''];
    }
}

$fails = 0;
$check = static function (bool $ok, string $name) use (&$fails): void {
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $fails++;
    }
};
$sk = 'sk_test_' . str_repeat('a', 32);

// create: auth, JSON, automatic idempotency key
$t = new FakeTransport([['status' => 201, 'headers' => [], 'body' => '{"id":"pay_1","object":"payment"}']]);
$p = (new Client($sk, ['transport' => $t, 'api_base' => 'https://api.test/']))->payments()->create(['amount' => '25.00', 'currency' => 'USD']);
$c = $t->calls[0];
$check($p['id'] === 'pay_1', 'create returns the payment');
$check($c['url'] === 'https://api.test/v1/payments', 'create url');
$check($c['headers']['Authorization'] === "Bearer $sk", 'bearer auth');
$check((bool) preg_match('/^[0-9a-f-]{36}$/', $c['headers']['Idempotency-Key']), 'automatic idempotency key');
$check(json_decode((string) $c['body'], true) === ['amount' => '25.00', 'currency' => 'USD'], 'json body');

// retry 503 with the same idempotency key
$t = new FakeTransport([['status' => 503, 'headers' => ['retry-after' => '0'], 'body' => '{}'], ['status' => 200, 'headers' => [], 'body' => '{"id":"pay_1"}']]);
(new Client($sk, ['transport' => $t, 'api_base' => 'https://api.test']))->request('POST', '/v1/payments/pay_1/cancel', [], null, 'k1');
$check(count($t->calls) === 2 && $t->calls[0]['headers']['Idempotency-Key'] === 'k1' && $t->calls[1]['headers']['Idempotency-Key'] === 'k1', 'retries keep the idempotency key');

// typed errors
$t = new FakeTransport([['status' => 400, 'headers' => [], 'body' => '{"error":{"type":"invalid_request_error","code":"amount_invalid","message":"bad","param":"amount","request_id":"req_1"}}']]);
try {
    (new Client($sk, ['transport' => $t, 'api_base' => 'https://api.test']))->payments()->create(['amount' => '-1', 'currency' => 'USD']);
    $check(false, 'api error thrown');
} catch (ApiError $e) {
    $check($e->status === 400 && $e->getErrorCode() === 'amount_invalid' && $e->param === 'amount' && $e->requestId === 'req_1', 'typed ApiError');
}

// secret keys only
try {
    new Client('pk_test_abc');
    $check(false, 'publishable key refused');
} catch (\RazCrypto\Exception\RazCryptoException) {
    $check(true, 'publishable key refused');
}

// Official host by default; RAZCRYPTO_API_BASE / api_base override it.
putenv('RAZCRYPTO_API_BASE');
$t = new FakeTransport(array_fill(0, 3, ['status' => 200, 'headers' => [], 'body' => '{"id":"pay_1","object":"payment"}']));
(new Client($sk, ['transport' => $t]))->payments()->retrieve('pay_1');
putenv('RAZCRYPTO_API_BASE=https://staging.example/v1/');
(new Client($sk, ['transport' => $t]))->payments()->retrieve('pay_1');
putenv('RAZCRYPTO_API_BASE');
(new Client($sk, ['transport' => $t, 'api_base' => 'https://custom.example']))->payments()->retrieve('pay_1');
$check($t->calls[0]['url'] === 'https://razcryptogateway.com/v1/payments/pay_1', 'default api host');
$check($t->calls[1]['url'] === 'https://staging.example/v1/payments/pay_1', 'RAZCRYPTO_API_BASE override');
$check($t->calls[2]['url'] === 'https://custom.example/v1/payments/pay_1', 'api_base override');

// Optional live smoke against a running API (test keys only).
$live = getenv('SK');
if (is_string($live) && str_starts_with($live, 'sk_test_')) {
    $rc = new Client($live, ['api_base' => getenv('API') ?: 'http://127.0.0.1:4000']);
    $pay = $rc->payments()->create(['amount' => '7.50', 'currency' => 'USD', 'reference_id' => 'php_' . time(), 'allowed_chains' => ['bsc']]);
    $q = $rc->payments()->quote($pay['id'], 'bsc', 'USDT');
    $s = $rc->payments()->simulate($pay['id'], 'paid');
    $check($s['status'] === 'paid' && is_string($q['quote']['amount']), 'live: create → quote → simulate paid');
}

echo $fails === 0 ? 'all passed' . PHP_EOL : "$fails failed" . PHP_EOL;
exit($fails === 0 ? 0 : 1);
