<?php

declare(strict_types=1);

namespace RazCrypto;

use RazCrypto\Exception\SignatureVerificationError;

/**
 * Checkout callback / redirect signature:
 *   hex(HMAC_SHA256(secret_key, payment_id . "|" . reference_id . "|" . status)), null reference_id => "".
 *
 * A valid signature proves the values came from RazCrypto; still check status === "paid" and that
 * the payment belongs to your order.
 */
final class CheckoutSignature
{
    /**
     * @param array{payment_id?: string, reference_id?: string|null, status?: string, signature?: string} $params
     *        wire names, e.g. straight from $_GET or the onSuccess JSON
     * @throws SignatureVerificationError
     */
    public static function verify(array $params, string $secretKey): bool
    {
        $message = ($params['payment_id'] ?? '') . '|' . ($params['reference_id'] ?? '') . '|' . ($params['status'] ?? '');
        $expected = hash_hmac('sha256', $message, $secretKey);
        $signature = $params['signature'] ?? '';
        if (!is_string($signature) || !hash_equals($expected, $signature)) {
            throw new SignatureVerificationError('Checkout signature does not match.', 'no_matching_signature');
        }
        return true;
    }

    /** @param array{payment_id?: string, reference_id?: string|null, status?: string, signature?: string} $params */
    public static function isValid(array $params, string $secretKey): bool
    {
        try {
            return self::verify($params, $secretKey);
        } catch (SignatureVerificationError) {
            return false;
        }
    }
}
