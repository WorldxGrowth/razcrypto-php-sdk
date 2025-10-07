<?php
namespace RazCrypto\Exceptions;

/**
 * Custom SDK exception for clear error messages upstream.
 */
class RazCryptoException extends \RuntimeException
{
    /** Optional machine-friendly code (e.g., RZ_002). */
    protected ?string $errorCode = null;

    public function __construct(string $message, ?string $errorCode = null, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
