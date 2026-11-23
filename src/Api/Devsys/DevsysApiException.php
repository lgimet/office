<?php

namespace App\Api\Devsys;

use RuntimeException;

final class DevsysApiException extends RuntimeException
{
    public function __construct(
        public readonly string $kind,
        public readonly ?int $httpStatus = null,
        string $message = 'Erreur lors de l’appel à l’API Devsys.',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
