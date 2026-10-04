<?php

declare(strict_types=1);

namespace RazCrypto\Resource;

use RazCrypto\Client;

final class PaymentLinks
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, mixed> $params title, amount_type, amount, currency, usage, collect, kind, …
     * @return array<string, mixed>
     */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        return $this->client->request('POST', '/v1/payment_links', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/payment_links/' . rawurlencode($id));
    }

    /**
     * @param array<string, scalar|null> $params limit, status, kind
     * @return array<string, mixed>
     */
    public function all(array $params = []): array
    {
        return $this->client->request('GET', '/v1/payment_links', $params);
    }

    /**
     * @param array<string, mixed> $params title, description, expires_at, redirect_url, metadata, status
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->client->request('PATCH', '/v1/payment_links/' . rawurlencode($id), [], $params);
    }

    /** @return array<string, mixed> */
    public function deactivate(string $id): array
    {
        return $this->client->request('POST', '/v1/payment_links/' . rawurlencode($id) . '/deactivate');
    }
}
