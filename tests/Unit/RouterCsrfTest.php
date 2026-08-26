<?php

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Core\Router;
use App\Services\AuthService;
use App\Services\Oidc\OidcSessionService;
use PHPUnit\Framework\TestCase;

final class RouterCsrfTest extends TestCase
{
    public function testPostLogoutWithoutValidCsrfIsRejectedBeforeController(): void
    {
        $router = new Router(new AuthService(new OidcSessionService()));

        try {
            $router->dispatch('POST', 'Auth/logout');
            self::fail('Le logout sans CSRF devait être rejeté.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('Token CSRF invalide', $exception->getMessage());
        }
    }
}
