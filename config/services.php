<?php

use App\Providers\DevsysApiProvider;
use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;

return static function (\App\Core\Container $container): void {
    $container->set(
        DevsysApiClient::class,
        static fn (): DevsysApiClient => DevsysApiProvider::create()
    );

    $container->autowire(ClientsApi::class);
};
