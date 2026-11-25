<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Api;

/**
 * Error reported by the eRecht24 API (or a transport failure, status code 0).
 * `getMessage()` is English, `$messageDe` German – both are fit for end users.
 */
final class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly string $messageDe = '',
        public readonly ?int $errorCode = null,
        public readonly ?string $faqLink = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
