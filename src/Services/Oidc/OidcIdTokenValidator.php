<?php

namespace App\Services\Oidc;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

final class OidcIdTokenValidator
{
    public function __construct(
        private readonly OidcHttpClientInterface $http,
        private readonly OidcDiscoveryProvider $discovery,
        private readonly string $issuer,
        private readonly string $clientId,
    ) {}

    public function validate(string $token, string $nonce): object
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new OidcValidationException('ID Token invalide.');
        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'RS256' || !is_string($header['kid'] ?? null) || $header['kid'] === '') throw new OidcValidationException('En-tête ID Token invalide.');
        $keys = $this->keys();
        if (!isset($keys[$header['kid']])) {
            $keys = $this->keys(true);
            if (!isset($keys[$header['kid']])) throw new OidcValidationException('Clé ID Token inconnue.');
        }
        try { $claims = JWT::decode($token, $keys[$header['kid']]); } catch (\Throwable $e) { throw new OidcValidationException('Signature ID Token invalide.', 0, $e); }
        $aud = $claims->aud ?? null;
        if (!(is_string($aud) ? $aud === $this->clientId : is_array($aud) && in_array($this->clientId, $aud, true))) throw new OidcValidationException('Audience ID Token invalide.');
        if (($claims->iss ?? null) !== $this->issuer || ($claims->nonce ?? null) !== $nonce || ($claims->token_use ?? null) !== 'id_token') throw new OidcValidationException('Claims ID Token invalides.');
        if (!isset($claims->exp) || (int) $claims->exp <= time()) throw new OidcValidationException('ID Token expiré.');
        if (isset($claims->iat) && abs(time() - (int) $claims->iat) > 300) throw new OidcValidationException('iat ID Token invalide.');
        if (!is_string($claims->sub ?? null) || !preg_match('/^user:[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $claims->sub)) throw new OidcValidationException('sub utilisateur invalide.');
        if (!is_string($claims->tenant_id ?? null) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $claims->tenant_id)) throw new OidcValidationException('tenant_id invalide.');
        return $claims;
    }

    private ?array $keyCache = null;
    private function keys(bool $refresh = false): array
    {
        if (!$refresh && $this->keyCache !== null) return $this->keyCache;
        $response = $this->http->getJson($this->discovery->get()['jwks_uri']);
        if ($response['status'] !== 200) throw new OidcValidationException('JWKS indisponible.');
        try { return $this->keyCache = JWK::parseKeySet($response['body'], 'RS256'); } catch (\Throwable $e) { throw new OidcValidationException('JWKS invalide.', 0, $e); }
    }
}
