<?php

declare(strict_types=1);

namespace RazCrypto;

use RazCrypto\Exception\ApiError;
use RazCrypto\Exception\ConnectionError;
use RazCrypto\Exception\RazCryptoException;
use RazCrypto\Http\CurlTransport;
use RazCrypto\Http\Transport;

/**
 * RazCrypto Gateway API client.
 *
 *   $rc = new \RazCrypto\Client(getenv('RAZCRYPTO_SECRET_KEY'));
 *   $payment = $rc->payments()->create(['amount' => '25.00', 'currency' => 'USD', 'reference_id' => 'order_1042']);
 *   header('Location: ' . $payment['checkout_url']);
 */
final class Client
{
    public const VERSION = '3.0.0';
    /** Official RazCrypto API host. The SDK adds /v1/... itself. */
    public const DEFAULT_API_BASE = 'https://razcryptogateway.com';

    private string $base;
    private Transport $transport;

    /**
     * @param array{api_base?: string, timeout?: int, max_retries?: int, transport?: Transport} $options
     */
    public function __construct(private readonly string $secretKey, private readonly array $options = [])
    {
        if (!preg_match('/^sk_(live|test)_/', $secretKey)) {
            throw new RazCryptoException('Pass your secret key (sk_live_… or sk_test_…).', 'invalid_key');
        }
        // Official host by default; api_base / RAZCRYPTO_API_BASE only for staging or a self-hosted gateway.
        $base = $options['api_base'] ?? (getenv('RAZCRYPTO_API_BASE') ?: self::DEFAULT_API_BASE);
        if (!is_string($base) || !preg_match('#^https?://#', $base)) {
            throw new RazCryptoException('api_base must be an http(s) URL.', 'invalid_api_base');
        }
        $this->base = preg_replace('#/v1$#', '', rtrim($base, '/'));
        $this->transport = $options['transport'] ?? new CurlTransport($options['timeout'] ?? 30);
    }

    public function payments(): Resource\Payments
    {
        return new Resource\Payments($this);
    }

    public function paymentLinks(): Resource\PaymentLinks
    {
        return new Resource\PaymentLinks($this);
    }

    /** @return array<string, mixed> */
    public function chains(): array
    {
        return $this->request('GET', '/v1/chains');
    }

    /**
     * @param list<string> $coins
     * @return array<string, mixed>
     */
    public function rates(array $coins, string $currency = 'USD'): array
    {
        return $this->request('GET', '/v1/rates', ['coins' => implode(',', $coins), 'currency' => $currency]);
    }

    /** @return array<string, mixed> */
    public function merchant(): array
    {
        return $this->request('GET', '/v1/merchant');
    }

    /** @return array<string, mixed> */
    public function event(string $id): array
    {
        return $this->request('GET', '/v1/events/' . rawurlencode($id));
    }

    /**
     * Low-level request with retries (network errors, 429, 5xx) and an automatic Idempotency-Key for POST.
     *
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, ?string $idempotencyKey = null): array
    {
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        $url = $this->base . $path . ($query ? '?' . http_build_query($query) : '');
        $headers = [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Accept' => 'application/json',
            'User-Agent' => 'razcrypto-php/' . self::VERSION . ' php/' . PHP_VERSION,
        ];
        $payload = null;
        if ($body !== null || $method === 'POST') {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body ?? new \stdClass(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        if ($method === 'POST') {
            $headers['Idempotency-Key'] = $idempotencyKey ?? self::uuid();
        }
        $maxRetries = $this->options['max_retries'] ?? 2;

        for ($attempt = 0; ; $attempt++) {
            try {
                $res = $this->transport->send($method, $url, $headers, $payload);
            } catch (ConnectionError $e) {
                if ($attempt < $maxRetries) {
                    usleep(self::backoff($attempt));
                    continue;
                }
                throw $e;
            }
            if (($res['status'] === 429 || $res['status'] >= 500) && $attempt < $maxRetries) {
                $after = (int) ($res['headers']['retry-after'] ?? 0);
                usleep($after > 0 ? min($after, 30) * 1_000_000 : self::backoff($attempt));
                continue;
            }
            $json = $res['body'] === '' ? [] : json_decode($res['body'], true);
            if (!is_array($json)) {
                $json = [];
            }
            if ($res['status'] >= 400) {
                throw ApiError::fromResponse($res['status'], $json['error'] ?? [], $res['headers']['request-id'] ?? null);
            }
            return $json;
        }
    }

    private static function backoff(int $attempt): int
    {
        return (int) (min(8000, 500 * (2 ** $attempt)) * (0.5 + mt_rand() / mt_getrandmax() / 2) * 1000);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
