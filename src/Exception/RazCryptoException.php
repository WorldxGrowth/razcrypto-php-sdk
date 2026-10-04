<?php

declare(strict_types=1);

namespace RazCrypto\Exception;

/** Base class for every SDK error. API error classes are added with the HTTP client. */
class RazCryptoException extends \Exception
{
    public function __construct(string $message, private readonly string $errorCode)
    {
        parent::__construct($message);
    }

    /** Machine-readable code, e.g. "no_matching_signature". */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
