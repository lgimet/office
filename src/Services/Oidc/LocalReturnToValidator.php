<?php

namespace App\Services\Oidc;

final class LocalReturnToValidator
{
    public function validate(mixed $returnTo, string $default = '/dashboard'): string
    {
        if (!is_string($returnTo) || $returnTo === '') return $default;
        if ($returnTo[0] !== '/' || str_starts_with($returnTo, '//') || str_starts_with($returnTo, '\\') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $returnTo) || str_contains($returnTo, '#')) return $default;
        return $returnTo;
    }
}
