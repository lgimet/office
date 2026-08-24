<?php

namespace App\Services\Oidc;

final class OidcDiscoveryProvider
{
    private ?array $discovery = null;

    public function __construct(
        private readonly OidcHttpClientInterface $http,
        private readonly string $issuer,
        private readonly int $cacheTtl = 300,
    ) {}

    /** @return array{issuer:string,authorization_endpoint:string,token_endpoint:string,userinfo_endpoint:string,jwks_uri:string} */
    public function get(): array
    {
        if ($this->discovery !== null) return $this->discovery;
        $cache = dirname(__DIR__, 3) . '/var/cache/oidc-discovery.json';
        if (is_file($cache) && filemtime($cache) !== false && filemtime($cache) + $this->cacheTtl > time()) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached)) {
                try { return $this->discovery = $this->validate($cached); } catch (OidcValidationException) {}
            }
        }
        $response = $this->http->getJson(rtrim($this->issuer, '/') . '/.well-known/openid-configuration');
        if ($response['status'] !== 200) throw new OidcProtocolException('La discovery OIDC a échoué.');
        $this->discovery = $this->validate($response['body']);
        if (!is_dir(dirname($cache))) @mkdir(dirname($cache), 0700, true);
        @file_put_contents($cache, json_encode($this->discovery, JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($cache, 0600);
        return $this->discovery;
    }

    private function validate(array $data): array
    {
        $required = ['issuer', 'authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri'];
        foreach ($required as $key) if (!is_string($data[$key] ?? null) || $data[$key] === '') throw new OidcValidationException('Discovery OIDC incomplète.');
        if (!hash_equals(rtrim($this->issuer, '/'), rtrim($data['issuer'], '/'))) throw new OidcValidationException('Issuer OIDC inattendu.');
        foreach (array_slice($required, 1) as $key) if (parse_url($data[$key], PHP_URL_SCHEME) !== 'https' && ($_ENV['APP_ENV'] ?? 'prod') === 'prod') throw new OidcValidationException('Endpoint OIDC non sécurisé.');
        return array_intersect_key($data, array_flip($required));
    }
}
