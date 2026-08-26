<?php
namespace App\Services\Oidc;
class OidcTokenRefresher
{
    public function __construct(private readonly OidcHttpClientInterface $http, private readonly OidcDiscoveryProvider $discovery, private readonly OidcSessionService $sessions, private readonly string $clientId, private readonly string $clientSecret) {}
    public function refresh(): string
    {
        $current = $this->sessions->tokenSet();
        if ($current === null) throw new OidcProtocolException('Aucun refresh token OIDC disponible.');
        $response = $this->http->postForm($this->discovery->get()['token_endpoint'], ['grant_type'=>'refresh_token','refresh_token'=>$current['refresh_token']], [$this->clientId, $this->clientSecret]);
        $body = $response['body'] ?? [];
        if ($response['status'] !== 200 || !is_string($body['access_token'] ?? null) || $body['access_token'] === '' || !is_string($body['refresh_token'] ?? null) || $body['refresh_token'] === '' || ($body['token_type'] ?? '') !== 'Bearer' || !is_numeric($body['expires_in'] ?? null) || (int)$body['expires_in'] <= 0) throw new OidcProtocolException('La réponse de renouvellement OIDC est invalide.');
        $scopes = null;
        if (array_key_exists('scope', $body)) { if (!is_string($body['scope'])) throw new OidcProtocolException('Les scopes du renouvellement OIDC sont invalides.'); $scopes = preg_split('/\s+/', trim($body['scope'])) ?: []; }
        $this->sessions->storeTokenSet($body['access_token'], $body['refresh_token'], (int)$body['expires_in'], $scopes);
        return $body['access_token'];
    }
}
