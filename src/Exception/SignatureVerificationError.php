<?php

declare(strict_types=1);

namespace RazCrypto\Exception;

/**
 * Raised locally by Webhook::verify() and CheckoutSignature::verify().
 * Codes: missing_header, invalid_timestamp, timestamp_outside_tolerance, invalid_secret,
 * no_matching_signature, invalid_payload.
 */
final class SignatureVerificationError extends RazCryptoException
{
}
