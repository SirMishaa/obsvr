<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class TwitchApiException extends RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ?int $statusCode = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
