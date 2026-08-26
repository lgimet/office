<?php

namespace Tests\Unit;

use App\Services\Oidc\OidcAccessDeniedException;
use App\Services\Oidc\OidcErrorContext;
use App\Services\Oidc\OidcFlowExpiredException;
use App\Services\Oidc\OidcIdentityException;
use App\Services\Oidc\OidcProtocolException;
use App\Services\Oidc\OidcStateInvalidException;
use App\Services\Oidc\OidcTransportException;
use PHPUnit\Framework\TestCase;

final class OidcErrorContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_save_path(sys_get_temp_dir());
        session_name('oidc_error_test');
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

    public function testContextIsSafeShortLivedAndConsumed(): void
    {
        OidcErrorContext::store('oidc_provider_unavailable');
        self::assertSame('oidc_provider_unavailable', OidcErrorContext::consume());
        self::assertArrayNotHasKey('oidc_error', $_SESSION);
        self::assertSame('oidc_unexpected_error', OidcErrorContext::consume());
    }

    public function testUnknownExpiredAndExternalDetailsBecomeGeneric(): void
    {
        OidcErrorContext::store('error_description=<script>alert(1)</script>');
        self::assertSame('oidc_unexpected_error', OidcErrorContext::consume());
        $_SESSION['oidc_error'] = ['code' => 'oidc_access_denied', 'created_at' => time() - 301];
        self::assertSame('oidc_unexpected_error', OidcErrorContext::consume());
        self::assertStringNotContainsString('error_description', OidcErrorContext::message('oidc_unexpected_error'));
    }

    /** @dataProvider classifications */
    public function testExceptionsHaveClosedUxClassifications(\Throwable $exception, string $code): void
    {
        self::assertSame($code, OidcErrorContext::classify($exception));
    }

    public static function classifications(): array
    {
        return [
            [new OidcAccessDeniedException('external error_description must not be shown'), 'oidc_access_denied'],
            [new OidcFlowExpiredException('technical detail'), 'oidc_flow_expired'],
            [new OidcStateInvalidException('technical detail'), 'oidc_state_invalid'],
            [new OidcTransportException('technical detail'), 'oidc_provider_unavailable'],
            [new OidcIdentityException('technical detail'), 'oidc_identity_invalid'],
            [new OidcProtocolException('technical detail'), 'oidc_response_invalid'],
            [new \RuntimeException('technical detail'), 'oidc_unexpected_error'],
        ];
    }
}
