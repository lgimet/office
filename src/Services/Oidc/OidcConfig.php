<?php
namespace App\Services\Oidc;

final class OidcConfig
{
    public function __construct(public readonly string $issuer, public readonly string $clientId, public readonly string $clientSecret, public readonly string $redirectUri, public readonly string $resource, public readonly array $scopes, public readonly float $httpTimeout, public readonly float $connectTimeout) {}
    public static function fromEnvironment(): self
    {
        $required = static function (string $name): string { $value=trim((string)($_ENV[$name]??'')); if($value==='') throw new \RuntimeException("La configuration OIDC $name est requise."); return $value; };
        $positive = static function (string $name, float $default): float { $value=$_ENV[$name]??$default; if(!is_numeric($value)||(float)$value<=0) throw new \RuntimeException("La configuration OIDC $name doit être positive."); return (float)$value; };
        $scopes = preg_split('/\s+/', $required('OFFICE_OIDC_SCOPES')) ?: [];
        return new self($required('OFFICE_OIDC_ISSUER'),$required('OFFICE_OIDC_CLIENT_ID'),$required('OFFICE_OIDC_CLIENT_SECRET'),$required('OFFICE_OIDC_REDIRECT_URI'),$required('OFFICE_OIDC_RESOURCE'),$scopes,$positive('OFFICE_OIDC_HTTP_TIMEOUT',10),$positive('OFFICE_OIDC_CONNECT_TIMEOUT',5));
    }
}
