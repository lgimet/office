<?php

namespace Tests\Unit;

use App\Services\Oidc\OidcAccessDeniedException;
use App\Services\Oidc\OidcErrorContext;
use App\Services\Oidc\OidcFlowExpiredException;
use App\Services\Oidc\OidcIdentityException;
use App\Services\Oidc\OidcProtocolException;
use App\Services\Oidc\OidcStateInvalidException;
use App\Services\Oidc\OidcTransportException;
use App\Services\Oidc\OidcValidationException;
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

    public function testLogIncludesOnePreviousExceptionWithoutTraceDump(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'oidc-log-');
        ini_set('error_log', $logFile);
        $exception = new OidcIdentityException(
            'Identité OIDC invalide.',
            0,
            new OidcValidationException('Audience ID Token invalide.')
        );

        OidcErrorContext::log($exception, 'oidc_identity_invalid');
        $log = file_get_contents($logFile);

        self::assertStringContainsString('OIDC callback oidc_identity_invalid', $log);
        self::assertStringContainsString('OidcValidationException: Audience ID Token invalide.', $log);
        self::assertStringNotContainsString('getTraceAsString', $log);
        unlink($logFile);
    }

    public function testLogIncludesNestedPreviousExceptionsAndRedactsSensitiveValues(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'oidc-log-');
        ini_set('error_log', $logFile);
        $secret = 'refresh-secret-value';
        $exception = new OidcIdentityException(
            'Identité OIDC invalide. access_token=access-secret',
            0,
            new OidcValidationException(
                'Signature ID Token invalide.',
                0,
                new \RuntimeException("refresh_token={$secret} client_secret=client-secret")
            )
        );

        OidcErrorContext::log($exception, 'oidc_identity_invalid');
        $log = file_get_contents($logFile);

        self::assertStringContainsString('OidcValidationException: Signature ID Token invalide.', $log);
        self::assertStringContainsString('RuntimeException: refresh_token=[redacted] client_secret=[redacted]', $log);
        self::assertStringNotContainsString('access-secret', $log);
        self::assertStringNotContainsString($secret, $log);
        self::assertStringNotContainsString('client-secret', $log);
        unlink($logFile);
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
