<?php

declare(strict_types=1);

namespace RazCrypto\Resource;

use RazCrypto\Client;

final class Payments
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, mixed> $params amount, currency, reference_id, description, customer, allowed_chains,
     *        allowed_coins, redirect_url, cancel_url, expires_in, metadata
     * @return array<string, mixed>
     */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        return $this->client->request('POST', '/v1/payments', [], $params, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/payments/' . rawurlencode($id));
    }

    /**
     * @param array<string, scalar|null> $params limit, starting_after, status, reference_id, chain, coin
     * @return array<string, mixed>
     */
    public function all(array $params = []): array
    {
        return $this->client->request('GET', '/v1/payments', $params);
    }

    /** @return array<string, mixed> */
    public function methods(string $id): array
    {
        return $this->client->request('GET', '/v1/payments/' . rawurlencode($id) . '/methods');
    }

    /** @return array<string, mixed> */
    public function quote(string $id, string $chain, string $coin): array
    {
        return $this->client->request('POST', '/v1/payments/' . rawurlencode($id) . '/quote', [], ['chain' => $chain, 'coin' => $coin]);
    }

    /** @return array<string, mixed> */
    public function cancel(string $id): array
    {
        return $this->client->request('POST', '/v1/payments/' . rawurlencode($id) . '/cancel');
    }

    /**
     * Test mode only: 'paid' | 'underpaid' | 'overpaid' | 'expired' | 'cancelled'.
     * @return array<string, mixed>
     */
    public function simulate(string $id, string $outcome): array
    {
        return $this->client->request('POST', '/v1/payments/' . rawurlencode($id) . '/simulate', [], ['outcome' => $outcome]);
    }
}
