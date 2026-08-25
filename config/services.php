<?php

use App\Providers\DevsysApiProvider;
use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;
use App\Providers\OfficeAccessTokenProvider;
use App\Services\Oidc\{CurlOidcHttpClient, LocalReturnToValidator, OidcClient, OidcConfig, OidcDiscoveryProvider, OidcHttpClientInterface, OidcIdTokenValidator, OidcSessionService};

return static function (\App\Core\Container $container): void {
    $container->set(
        DevsysApiClient::class,
        static fn (): DevsysApiClient => DevsysApiProvider::create(new OfficeAccessTokenProvider(new OidcSessionService()))
    );

    $container->set(OidcConfig::class, static fn (): OidcConfig => OidcConfig::fromEnvironment());
    $container->set(OidcHttpClientInterface::class, static fn ($c): OidcHttpClientInterface => new CurlOidcHttpClient($c->get(OidcConfig::class)->httpTimeout, $c->get(OidcConfig::class)->connectTimeout));
    $container->set(OidcDiscoveryProvider::class, static fn ($c): OidcDiscoveryProvider => new OidcDiscoveryProvider($c->get(OidcHttpClientInterface::class), $c->get(OidcConfig::class)->issuer));
    $container->set(OidcIdTokenValidator::class, static fn ($c): OidcIdTokenValidator => new OidcIdTokenValidator($c->get(OidcHttpClientInterface::class), $c->get(OidcDiscoveryProvider::class), $c->get(OidcConfig::class)->issuer, $c->get(OidcConfig::class)->clientId));
    $container->set(OidcClient::class, static fn ($c): OidcClient => new OidcClient($c->get(OidcHttpClientInterface::class), $c->get(OidcDiscoveryProvider::class), $c->get(OidcIdTokenValidator::class), new OidcSessionService(), new LocalReturnToValidator(), $c->get(OidcConfig::class)->clientId, $c->get(OidcConfig::class)->clientSecret, $c->get(OidcConfig::class)->redirectUri, $c->get(OidcConfig::class)->issuer, $c->get(OidcConfig::class)->resource, $c->get(OidcConfig::class)->scopes));

    $container->autowire(ClientsApi::class);
};
