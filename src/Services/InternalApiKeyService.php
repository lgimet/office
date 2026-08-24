<?php

namespace App\Services;

class InternalApiKeyService
{
    public function isValid(): bool
    {
        $expectedKey = (string) ($_ENV['API_INTERNAL_KEY'] ?? '');
        $authorization = (string) (
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? ''
        );

        if ($expectedKey === '' || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return false;
        }

        return hash_equals($expectedKey, trim($matches[1]));
    }
}
