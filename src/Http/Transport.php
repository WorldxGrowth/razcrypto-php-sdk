<?php

declare(strict_types=1);

namespace RazCrypto\Http;

/** Pluggable HTTP layer (cURL by default; the WooCommerce plugin uses wp_remote_request). */
interface Transport
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string} header names lower-case
     * @throws \RazCrypto\Exception\ConnectionError
     */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}
