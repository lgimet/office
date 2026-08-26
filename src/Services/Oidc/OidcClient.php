<?php

namespace App\Services\Oidc;

final class OidcClient
{
    public function __construct(private readonly OidcHttpClientInterface $http, private readonly OidcDiscoveryProvider $discovery, private readonly OidcIdTokenValidator $validator, private readonly OidcSessionService $sessions, private readonly LocalReturnToValidator $returnTo, private readonly string $clientId, private readonly string $clientSecret, private readonly string $redirectUri, private readonly string $issuer, private readonly string $resource, private readonly array $scopes) {}
    public function authorizationUrl(mixed $target): string
    {
        $state = $this->b64(random_bytes(32)); $nonce = $this->b64(random_bytes(32)); $verifier = $this->b64(random_bytes(64));
        $_SESSION['oidc_pending'] = ['state'=>$state,'nonce'=>$nonce,'code_verifier'=>$verifier,'return_to'=>$this->returnTo->validate($target),'created_at'=>time()];
        $query = ['response_type'=>'code','client_id'=>$this->clientId,'redirect_uri'=>$this->redirectUri,'scope'=>implode(' ', $this->scopes),'state'=>$state,'nonce'=>$nonce,'code_challenge'=>$this->b64(hash('sha256',$verifier,true)),'code_challenge_method'=>'S256','resource'=>$this->resource];
        return $this->discovery->get()['authorization_endpoint'] . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    public function callback(array $query): string
    {
        $pending = $_SESSION['oidc_pending'] ?? null; $_SESSION['oidc_pending'] = null;
        if (!is_array($pending) || (int) ($pending['created_at'] ?? 0) + 600 < time()) throw new OidcFlowExpiredException('Flow OIDC expiré.');
        if (!is_string($query['state'] ?? null) || !hash_equals((string)$pending['state'], $query['state'])) throw new OidcStateInvalidException('État OIDC invalide.');
        if (($query['error'] ?? null) === 'access_denied') throw new OidcAccessDeniedException('La connexion DevSys a été refusée.');
        if (isset($query['error']) || !is_string($query['code'] ?? null) || $query['code'] === '') throw new OidcProtocolException('La connexion DevSys a été refusée.');
        $token = $this->http->postForm($this->discovery->get()['token_endpoint'], ['grant_type'=>'authorization_code','client_id'=>$this->clientId,'code'=>$query['code'],'redirect_uri'=>$this->redirectUri,'code_verifier'=>$pending['code_verifier']], [$this->clientId,$this->clientSecret]);
        $body = $token['body']; if ($token['status'] !== 200 || !is_string($body['access_token'] ?? null) || ($body['token_type'] ?? '') !== 'Bearer' || !is_numeric($body['expires_in'] ?? null) || (int)$body['expires_in'] <= 0 || !is_string($body['id_token'] ?? null)) throw new OidcProtocolException('La réponse OAuth est invalide.');
        try { $claims = $this->validator->validate($body['id_token'], $pending['nonce']); } catch (OidcValidationException $exception) { throw new OidcIdentityException('Identité OIDC invalide.', 0, $exception); } $info = $this->http->getBearer($this->discovery->get()['userinfo_endpoint'], $body['access_token']);
        if ($info['status'] !== 200) throw new OidcProtocolException('UserInfo est indisponible.');
        $this->sessions->create($claims, $info['body'], (int)$body['expires_in'], preg_split('/\s+/', (string)($body['scope'] ?? implode(' ', $this->scopes))) ?: []); $this->sessions->storeAccessToken($body['access_token'], (int)$body['expires_in']);
        return $this->returnTo->validate($pending['return_to']);
    }
    private function b64(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
}
