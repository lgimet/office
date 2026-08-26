<?php

namespace App\Services\Oidc;

final class OidcErrorContext
{
    private const SESSION_KEY = 'oidc_error';
    private const TTL = 300;

    private const MESSAGES = [
        'oidc_access_denied' => 'La connexion a été annulée ou refusée.',
        'oidc_flow_expired' => 'La tentative de connexion a expiré. Veuillez réessayer.',
        'oidc_state_invalid' => "La tentative de connexion n'est plus valide. Veuillez réessayer.",
        'oidc_provider_unavailable' => 'Le service de connexion DevSys est temporairement indisponible.',
        'oidc_response_invalid' => "La réponse du service de connexion n'a pas pu être validée.",
        'oidc_identity_invalid' => "Votre identité n'a pas pu être validée.",
        'oidc_unexpected_error' => 'Une erreur inattendue a empêché la connexion.',
    ];

    public static function store(string $code): void
    {
        if (!isset(self::MESSAGES[$code])) {
            $code = 'oidc_unexpected_error';
        }
        $_SESSION[self::SESSION_KEY] = ['code' => $code, 'created_at' => time()];
    }

    public static function consume(): string
    {
        $context = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_array($context) || !isset(self::MESSAGES[$context['code'] ?? '']) || !is_int($context['created_at'] ?? null) || $context['created_at'] + self::TTL < time()) {
            return 'oidc_unexpected_error';
        }
        return $context['code'];
    }

    public static function message(string $code): string
    {
        return self::MESSAGES[$code] ?? self::MESSAGES['oidc_unexpected_error'];
    }

    public static function classify(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof OidcAccessDeniedException => 'oidc_access_denied',
            $exception instanceof OidcFlowExpiredException, $exception instanceof OidcSessionExpiredException => 'oidc_flow_expired',
            $exception instanceof OidcStateInvalidException => 'oidc_state_invalid',
            $exception instanceof OidcTransportException => 'oidc_provider_unavailable',
            $exception instanceof OidcIdentityException => 'oidc_identity_invalid',
            $exception instanceof OidcProtocolException => 'oidc_response_invalid',
            default => 'oidc_unexpected_error',
        };
    }

    public static function log(\Throwable $exception, string $code): void
    {
        error_log(sprintf('[%s] OIDC callback %s: %s: %s%s%s', date('c'), $code, $exception::class, $exception->getMessage(), PHP_EOL, $exception->getTraceAsString()));
    }
}
