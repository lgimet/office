<?php

namespace App\Exceptions;

use InvalidArgumentException;

class ApiValidationException extends InvalidArgumentException
{
    public function __construct(string $message, private array $errors)
    {
        parent::__construct($message);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
