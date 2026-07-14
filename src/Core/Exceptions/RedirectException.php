<?php

namespace App\Core\Exceptions;

use RuntimeException;

class RedirectException extends RuntimeException
{
    public function __construct(
        private readonly string $location,
        private readonly int $statusCode = 302
    ) {
        parent::__construct(sprintf('Redirect to %s', $location));
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
