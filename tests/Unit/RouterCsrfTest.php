<?php

namespace Tests\Unit;

use App\Core\Exceptions\HttpException;
use App\Core\App;
use App\Core\Container;
use App\Core\Router;
use App\Services\AuthService;
use App\Services\Oidc\{OidcSessionService, OidcTokenRefresher};
use App\Providers\OfficeAccessTokenProvider;
use PHPUnit\Framework\TestCase;

final class RouterCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_save_path(sys_get_temp_dir());
        session_name('router_auth_test');
        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        parent::tearDown();
    }

    public function testPostLogoutWithoutValidCsrfIsRejectedBeforeController(): void
    {
        $sessions = new OidcSessionService();
        $router = new Router(new AuthService($sessions, new OfficeAccessTokenProvider($sessions, $this->createMock(OidcTokenRefresher::class))));

        try {
            $router->dispatch('POST', 'Auth/logout');
            self::fail('Le logout sans CSRF devait être rejeté.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('Token CSRF invalide', $exception->getMessage());
        }
    }

    public function testAuthRequiredRouteRunsAfterOneSilentRefresh(): void
    {
        $sessions = new OidcSessionService();
        $claims = (object) [
            'sub' => 'user:123e4567-e89b-12d3-a456-426614174000',
            'tenant_id' => '123e4567-e89b-12d3-a456-426614174001',
        ];
        $sessions->create($claims, [
            'sub' => $claims->sub,
            'tenant_id' => $claims->tenant_id,
            'given_name' => 'Ada',
            'family_name' => 'Lovelace',
        ], 3600, ['openid']);
        $sessions->storeTokenSet('access-a', 'refresh-a', 1);

        $refresher = $this->createMock(OidcTokenRefresher::class);
        $refresher->expects(self::once())->method('refresh')->willReturnCallback(function () use ($sessions): string {
            $sessions->storeTokenSet('access-b', 'refresh-b', 3600, ['openid']);

            return 'access-b';
        });
        $auth = new AuthService($sessions, new OfficeAccessTokenProvider($sessions, $refresher));
        $container = new Container();
        $container->set(AuthService::class, static fn (): AuthService => $auth);
        App::setContainer($container);
        $router = new Router($auth);

        ob_start();
        try {
            $router->dispatch('GET', 'DashboardController/demoOptions');
            $output = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringContainsString('"success":true', $output);
        self::assertSame('access-b', $_SESSION['office_oauth']['access_token']);
    }
}
