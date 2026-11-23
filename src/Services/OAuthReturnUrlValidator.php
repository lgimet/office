<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Autorise uniquement le retour vers l'endpoint OAuth Devsys configuré.
 *
 * Le paramètre return_to provient d'un navigateur et ne doit jamais devenir
 * une redirection ouverte après une connexion Office.
 */
final class OAuthReturnUrlValidator
{
    public function validate(mixed $returnTo): ?string
    {
        if (!is_string($returnTo) || $returnTo === '') {
            return null;
        }

        $configuredUrl = (string) ($_ENV['OAUTH_AUTHORIZE_URL'] ?? '');
        $expected = parse_url($configuredUrl);
        $candidate = parse_url($returnTo);

        if (!is_array($expected) || !is_array($candidate)) {
            return null;
        }

        if (
            ($expected['scheme'] ?? null) !== 'https'
            || ($candidate['scheme'] ?? null) !== $expected['scheme']
            || ($candidate['host'] ?? null) !== $expected['host']
            || $this->port($candidate) !== $this->port($expected)
            || ($candidate['path'] ?? '/') !== ($expected['path'] ?? '/')
            || isset($candidate['user'])
            || isset($candidate['pass'])
            || isset($candidate['fragment'])
        ) {
            return null;
        }

        return $returnTo;
    }

    /**
     * Normalise les ports implicites HTTPS afin de comparer les URLs de façon
     * fiable sans élargir la cible de redirection.
     *
     * @param array<string, mixed> $url
     */
    private function port(array $url): int
    {
        return (int) ($url['port'] ?? (($url['scheme'] ?? null) === 'https' ? 443 : 80));
    }
}
