<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

abstract class TwitchException extends RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly ?int $statusCode = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    final public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
