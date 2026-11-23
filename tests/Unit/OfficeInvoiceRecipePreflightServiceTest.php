<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InvoiceDraftApiInterface;
use App\Services\OfficeInvoiceRecipePreflightService;
use App\Services\Oidc\OidcSessionService;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiAuthenticationException;
use PHPUnit\Framework\TestCase;

final class OfficeInvoiceRecipePreflightServiceTest extends TestCase
{
    private const SUBJECT = 'user:aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'], $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT']);
        parent::tearDown();
    }

    public function testOffDoesNotCallApi(): void
    {
        unset($_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'], $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT']);
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = $this->service($api, ['sub' => self::SUBJECT]);
        self::assertFalse($service->isEnabledForCurrentOperator());
        self::assertSame('UNAVAILABLE', $service->check());
    }

    public function testServerAllowlistedOperatorUsesApiSdkAndGetsOnlyPass(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::once())->method('recipePreflight');
        $service = $this->service($api, ['sub' => self::SUBJECT]);
        self::assertTrue($service->isEnabledForCurrentOperator());
        self::assertSame('PASS', $service->check());
    }

    public function testDifferentOrMalformedSessionSubjectDoesNotCallApi(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = $this->service($api, ['sub' => 'user:bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']);
        self::assertFalse($service->isEnabledForCurrentOperator());
        self::assertSame('UNAVAILABLE', $service->check());
    }

    public function testApiAuthenticationRefusalIsRedacted(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::once())->method('recipePreflight')->willThrowException(new DevsysApiAuthenticationException('redacted', 403, '/invoices/recipe-preflight'));
        $service = $this->service($api, ['sub' => self::SUBJECT]);
        self::assertSame('REFUSED', $service->check());
    }

    private function service(InvoiceDraftApiInterface $api, ?array $identity): OfficeInvoiceRecipePreflightService
    {
        $sessions = $this->createMock(OidcSessionService::class);
        $sessions->method('identity')->willReturn($identity);
        return new OfficeInvoiceRecipePreflightService($api, $sessions);
    }
}
