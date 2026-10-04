<?php

declare(strict_types=1);

namespace RazCrypto;

use RazCrypto\Exception\SignatureVerificationError;

/**
 * Standard Webhooks verification (https://www.standardwebhooks.com/).
 *
 *   $event = Webhook::verify($request->getContent(), $request->headers->all(), $secret);
 */
final class Webhook
{
    private const PREFIX = 'whsec_';
    private const BASE64_RE = '~^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$~';

    /**
     * Verify a webhook request and return the decoded event envelope as an array.
     *
     * @param string $rawBody exact request body bytes (e.g. file_get_contents('php://input'))
     * @param iterable<string, string|string[]>|object $headers header map; names are matched case-insensitively.
     *        Accepts arrays (incl. Symfony/Laravel headers->all() with array values) or a PSR-7 request.
     * @param int $toleranceSec maximum |now - webhook-timestamp| in seconds
     * @param int|null $now clock override (unix seconds), for tests only
     * @return array<string, mixed>
     * @throws SignatureVerificationError
     */
    public static function verify(string $rawBody, iterable|object $headers, string $secret, int $toleranceSec = 300, ?int $now = null): array
    {
        $key = self::decodeSecret($secret);

        $id = self::header($headers, 'webhook-id');
        $ts = self::header($headers, 'webhook-timestamp');
        $sigHeader = self::header($headers, 'webhook-signature');
        if ($id === null || $id === '' || $ts === null || $ts === '' || $sigHeader === null || $sigHeader === '') {
            throw new SignatureVerificationError('Missing webhook-id, webhook-timestamp or webhook-signature header.', 'missing_header');
        }
        if (preg_match('/^[0-9]+$/D', $ts) !== 1) {
            throw new SignatureVerificationError('webhook-timestamp is not an integer.', 'invalid_timestamp');
        }

        $now ??= time();
        if (abs($now - (int) $ts) > $toleranceSec) {
            throw new SignatureVerificationError('webhook-timestamp is outside the allowed tolerance.', 'timestamp_outside_tolerance');
        }

        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $ts . '.' . $rawBody, $key, true));
        $matched = false;
        foreach (explode(' ', $sigHeader) as $entry) {
            $parts = explode(',', $entry, 2);
            if (count($parts) !== 2 || $parts[0] !== 'v1') {
                continue;
            }
            if (hash_equals($expected, $parts[1])) {
                $matched = true;
            }
        }
        if (!$matched) {
            throw new SignatureVerificationError('No matching v1 signature.', 'no_matching_signature');
        }

        try {
            $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SignatureVerificationError('Webhook body is not valid JSON.', 'invalid_payload');
        }
        if (!is_array($event)) {
            throw new SignatureVerificationError('Webhook body is not a JSON object.', 'invalid_payload');
        }
        return $event;
    }

    private static function decodeSecret(string $secret): string
    {
        $b64 = str_starts_with($secret, self::PREFIX) ? substr($secret, strlen(self::PREFIX)) : $secret;
        $key = $b64 === '' || preg_match(self::BASE64_RE, $b64) !== 1 ? false : base64_decode($b64, true);
        if ($key === false || $key === '') {
            throw new SignatureVerificationError('Webhook secret is not "whsec_" followed by valid base64.', 'invalid_secret');
        }
        return $key;
    }

    /** @param iterable<string, mixed>|object $headers */
    private static function header(iterable|object $headers, string $name): ?string
    {
        // PSR-7 MessageInterface (duck-typed to avoid a psr/http-message dependency)
        if (is_object($headers) && method_exists($headers, 'getHeaderLine')) {
            $line = $headers->getHeaderLine($name);
            return $line === '' ? null : $line;
        }
        if (!is_iterable($headers)) {
            return null;
        }
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === $name) {
                if (is_array($v)) {
                    $v = $v[array_key_first($v)] ?? null;
                }
                return is_string($v) ? $v : null;
            }
        }
        return null;
    }
}
