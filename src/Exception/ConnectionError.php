<?php

declare(strict_types=1);

namespace RazCrypto\Exception;

/** Network failure or timeout — safe to retry with the same Idempotency-Key. */
final class ConnectionError extends RazCryptoException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 'connection_error');
    }
}
