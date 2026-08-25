<?php

namespace App\Services\Oidc;

final class LocalReturnToValidator
{
    public function validate(mixed $returnTo, string $default = '/dashboard'): string
    {
        if (!is_string($returnTo) || $returnTo === '') return $default;
        if ($returnTo[0] !== '/' || str_starts_with($returnTo, '//') || str_contains($returnTo, '\\') || preg_match('/[\x00-\x1F\x7F]/', $returnTo) || preg_match('/^[a-z][a-z0-9+.-]*:/i', $returnTo) || str_contains($returnTo, '#')) return $default;
        $parsed = parse_url($returnTo);
        if (!is_array($parsed) || isset($parsed['scheme'], $parsed['host'], $parsed['user'], $parsed['pass'], $parsed['fragment'])) return $default;
        return $returnTo;
    }
}
