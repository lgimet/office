<?php

declare(strict_types=1);

namespace App\Providers;

use Devsys\Shared\Api\Devsys\Configuration\DevsysApiConfig;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;

final class DevsysApiProvider
{
    public static function create(): DevsysApiClient
    {
        return new DevsysApiClient(
            new DevsysApiConfig(
                baseUrl: self::requiredEnvironment('DEVSYS_API_BASE_URL'),
                accessToken: self::oauthTokenProvider()->accessToken(),
                timeout: self::positiveFloat('DEVSYS_API_TIMEOUT', 10.0),
                connectTimeout: self::positiveFloat('DEVSYS_API_CONNECT_TIMEOUT', 5.0),
            )
        );
    }

    private static function oauthTokenProvider(): DevsysOAuthTokenProvider
    {
        return new DevsysOAuthTokenProvider(
            tokenUrl: self::requiredEnvironment('DEVSYS_OAUTH_TOKEN_URL'),
            clientId: self::requiredEnvironment('DEVSYS_API_CLIENT_ID'),
            clientSecret: self::requiredEnvironment('DEVSYS_API_CLIENT_SECRET'),
            scope: self::requiredEnvironment('DEVSYS_API_SCOPE'),
            timeout: self::positiveFloat('DEVSYS_API_TIMEOUT', 10.0),
            connectTimeout: self::positiveFloat('DEVSYS_API_CONNECT_TIMEOUT', 5.0),
        );
    }

    private static function requiredEnvironment(string $name): string
    {
        $value = trim((string) ($_ENV[$name] ?? ''));

        if ($value === '') {
            throw new \RuntimeException(sprintf('La variable d’environnement %s est requise.', $name));
        }

        return $value;
    }

    private static function positiveFloat(string $name, float $default): float
    {
        $value = $_ENV[$name] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value) || (float) $value <= 0) {
            throw new \RuntimeException(sprintf(
                'La variable d’environnement %s doit être un nombre positif.',
                $name
            ));
        }

        return (float) $value;
    }
}
