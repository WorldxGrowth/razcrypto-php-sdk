<?php

declare(strict_types=1);

namespace RazCrypto\Exception;

/** Error returned by the API: `{ "error": { type, code, message, param, request_id } }`. */
final class ApiError extends RazCryptoException
{
    public function __construct(
        string $message,
        string $code,
        public readonly int $status,
        public readonly string $type,
        public readonly ?string $param,
        public readonly ?string $requestId,
    ) {
        parent::__construct($message, $code);
    }

    /** @param array<string, mixed> $error */
    public static function fromResponse(int $status, array $error, ?string $requestId): self
    {
        return new self(
            (string) ($error['message'] ?? "HTTP $status"),
            (string) ($error['code'] ?? 'api_error'),
            $status,
            (string) ($error['type'] ?? 'api_error'),
            isset($error['param']) ? (string) $error['param'] : null,
            isset($error['request_id']) ? (string) $error['request_id'] : $requestId,
        );
    }
}
