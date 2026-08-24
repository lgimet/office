<?php

use App\Providers\DevsysApiProvider;
use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;
use App\Providers\OfficeAccessTokenProvider;
use App\Services\Oidc\{CurlOidcHttpClient, LocalReturnToValidator, OidcClient, OidcDiscoveryProvider, OidcHttpClientInterface, OidcIdTokenValidator, OidcSessionService};

return static function (\App\Core\Container $container): void {
    $container->set(
        DevsysApiClient::class,
        static fn (): DevsysApiClient => DevsysApiProvider::create(new OfficeAccessTokenProvider(new OidcSessionService()))
    );

    $container->set(OidcHttpClientInterface::class, static fn (): OidcHttpClientInterface => new CurlOidcHttpClient((float)($_ENV['OFFICE_OIDC_HTTP_TIMEOUT'] ?? 10), (float)($_ENV['OFFICE_OIDC_CONNECT_TIMEOUT'] ?? 5)));
    $container->set(OidcDiscoveryProvider::class, static fn ($c): OidcDiscoveryProvider => new OidcDiscoveryProvider($c->get(OidcHttpClientInterface::class), (string)$_ENV['OFFICE_OIDC_ISSUER']));
    $container->set(OidcIdTokenValidator::class, static fn ($c): OidcIdTokenValidator => new OidcIdTokenValidator($c->get(OidcHttpClientInterface::class), $c->get(OidcDiscoveryProvider::class), (string)$_ENV['OFFICE_OIDC_ISSUER'], (string)$_ENV['OFFICE_OIDC_CLIENT_ID']));
    $container->set(OidcClient::class, static fn ($c): OidcClient => new OidcClient($c->get(OidcHttpClientInterface::class), $c->get(OidcDiscoveryProvider::class), $c->get(OidcIdTokenValidator::class), new OidcSessionService(), new LocalReturnToValidator(), (string)$_ENV['OFFICE_OIDC_CLIENT_ID'], (string)$_ENV['OFFICE_OIDC_CLIENT_SECRET'], (string)$_ENV['OFFICE_OIDC_REDIRECT_URI'], (string)$_ENV['OFFICE_OIDC_ISSUER'], (string)$_ENV['OFFICE_OIDC_RESOURCE'], preg_split('/\s+/', trim((string)$_ENV['OFFICE_OIDC_SCOPES'])) ?: []));

    $container->autowire(ClientsApi::class);
};
