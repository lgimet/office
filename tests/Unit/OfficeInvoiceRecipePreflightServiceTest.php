<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InvoiceDraftApiInterface;
use App\Services\OfficeInvoiceRecipePreflightService;
use App\Services\Oidc\OidcSessionService;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiAuthenticationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OfficeInvoiceRecipePreflightServiceTest extends TestCase
{
    private const SUBJECT = 'user:aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const NOW = 1_800_000_000;
    private const BOOT_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

    /** @var list<string> */
    private array $temporaryEnvironmentFiles = [];

    protected function tearDown(): void
    {
        unset($_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'], $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'], $_ENV['OFFICE_INVOICE_PREFLIGHT_STARTED_AT'], $_ENV['OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT'], $_ENV['OFFICE_INVOICE_PREFLIGHT_BOOT_ID']);
        foreach ($this->temporaryEnvironmentFiles as $path) {
            @unlink($path);
        }
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
        $this->setLease(self::NOW, self::NOW + 300);
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
        $this->setLease(self::NOW, self::NOW + 300);
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
        $this->setLease(self::NOW, self::NOW + 300);
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::once())->method('recipePreflight')->willThrowException(new DevsysApiAuthenticationException('redacted', 403, '/invoices/recipe-preflight'));
        $service = $this->service($api, ['sub' => self::SUBJECT]);
        self::assertSame('REFUSED', $service->check());
    }

    public function testMissingOrMalformedDeadlineDoesNotInspectSessionOrCallApi(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        unset($_ENV['OFFICE_INVOICE_PREFLIGHT_STARTED_AT'], $_ENV['OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT']);
        $sessions = $this->createMock(OidcSessionService::class);
        $sessions->expects(self::never())->method('identity');
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = new OfficeInvoiceRecipePreflightService($api, $sessions, static fn (): int => self::NOW);
        self::assertFalse($service->isEnabledForCurrentOperator());
        self::assertSame('UNAVAILABLE', $service->check());
    }

    public function testMaximumLeaseAcceptedAndLongerLeaseRejected(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $this->setLease(self::NOW, self::NOW + 300);
        $service = $this->service($this->createMock(InvoiceDraftApiInterface::class), ['sub' => self::SUBJECT], static fn (): int => self::NOW);
        self::assertTrue($service->isEnabledForCurrentOperator());

        $this->setLease(self::NOW, self::NOW + 301);
        self::assertFalse($service->isEnabledForCurrentOperator());
    }

    public function testExpiryIsAbsoluteAndSdkIsNotCalledAtOrAfterDeadline(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $this->setLease(self::NOW, self::NOW + 300);
        $now = self::NOW + 299;
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = $this->service($api, ['sub' => self::SUBJECT], static function () use (&$now): int { return $now; });
        self::assertTrue($service->isEnabledForCurrentOperator());

        $now = self::NOW + 300;
        self::assertFalse($service->isEnabledForCurrentOperator());
        self::assertSame('UNAVAILABLE', $service->check());
    }

    public function testSystemRebootInvalidatesLeaseBeforeExpiry(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $this->setLease(self::NOW, self::NOW + 300);
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = $this->service($api, ['sub' => self::SUBJECT], static fn (): int => self::NOW + 1, static fn (): string => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc');
        self::assertFalse($service->isEnabledForCurrentOperator());
        self::assertSame('UNAVAILABLE', $service->check());
    }

    public function testMalformedBootIdFailsClosed(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $this->setLease(self::NOW, self::NOW + 300);
        $_ENV['OFFICE_INVOICE_PREFLIGHT_BOOT_ID'] = 'not-a-boot-id';
        $sessions = $this->createMock(OidcSessionService::class);
        $sessions->expects(self::never())->method('identity');
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = new OfficeInvoiceRecipePreflightService($api, $sessions, static fn (): int => self::NOW, $this->temporaryEnvironmentFile(), static fn (): string => self::BOOT_ID);
        self::assertFalse($service->isEnabledForCurrentOperator());
    }

    public function testDuplicateDotenvLeaseAssignmentFailsClosedBeforeIdentityLookup(): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $this->setLease(self::NOW, self::NOW + 300);
        $file = $this->temporaryEnvironmentFile("OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT=1800000300\nOFFICE_INVOICE_PREFLIGHT_EXPIRES_AT=1800000300\n");
        $sessions = $this->createMock(OidcSessionService::class);
        $sessions->expects(self::never())->method('identity');
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = new OfficeInvoiceRecipePreflightService($api, $sessions, static fn (): int => self::NOW, $file, static fn (): string => self::BOOT_ID);
        self::assertFalse($service->isEnabledForCurrentOperator());
    }

    #[DataProvider('invalidLeaseProvider')]
    public function testMalformedLeaseValuesFailClosed(mixed $startedAt, mixed $expiresAt): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] = self::SUBJECT;
        $_ENV['OFFICE_INVOICE_PREFLIGHT_STARTED_AT'] = $startedAt;
        $_ENV['OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT'] = $expiresAt;
        $sessions = $this->createMock(OidcSessionService::class);
        $sessions->expects(self::never())->method('identity');
        $api = $this->createMock(InvoiceDraftApiInterface::class);
        $api->expects(self::never())->method('recipePreflight');
        $service = new OfficeInvoiceRecipePreflightService($api, $sessions, static fn (): int => self::NOW);
        self::assertFalse($service->isEnabledForCurrentOperator());
    }

    public static function invalidLeaseProvider(): array
    {
        return [
            'empty' => ['', ''],
            'negative' => ['-1', '1001'],
            'float' => ['1000.0', '1300'],
            'integer type' => [1000, '1300'],
            'leading whitespace' => [' 1000', '1300'],
            'trailing whitespace' => ['1000', "1300\n"],
            'plus sign' => ['+1000', '1300'],
            'leading zero' => ['01000', '01300'],
            'milliseconds' => ['1800000000000', '1800000000300'],
            'overflow' => ['999999999999999999999999999', '999999999999999999999999999'],
            'duplicated value encoding' => ["1000\n1001", '1300'],
            'expired' => [(string) (self::NOW - 300), (string) self::NOW],
            'equal to start' => [(string) self::NOW, (string) self::NOW],
            'future start' => [(string) (self::NOW + 1), (string) (self::NOW + 300)],
            'over maximum' => [(string) self::NOW, (string) (self::NOW + 301)],
        ];
    }

    private function service(InvoiceDraftApiInterface $api, ?array $identity, ?callable $clock = null, ?callable $bootIdProvider = null): OfficeInvoiceRecipePreflightService
    {
        $sessions = $this->createMock(OidcSessionService::class);
        $sessions->method('identity')->willReturn($identity);
        return new OfficeInvoiceRecipePreflightService($api, $sessions, $clock ?? static fn (): int => self::NOW, $this->temporaryEnvironmentFile(), $bootIdProvider ?? static fn (): string => self::BOOT_ID);
    }

    private function setLease(int $startedAt, int $expiresAt): void
    {
        $_ENV['OFFICE_INVOICE_PREFLIGHT_STARTED_AT'] = (string) $startedAt;
        $_ENV['OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT'] = (string) $expiresAt;
        $_ENV['OFFICE_INVOICE_PREFLIGHT_BOOT_ID'] = self::BOOT_ID;
    }

    private function temporaryEnvironmentFile(string $contents = "OFFICE_INVOICE_PREFLIGHT_ENABLED=false\nOFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT=\nOFFICE_INVOICE_PREFLIGHT_STARTED_AT=\nOFFICE_INVOICE_PREFLIGHT_EXPIRES_AT=\nOFFICE_INVOICE_PREFLIGHT_BOOT_ID=\n"): string
    {
        $file = tempnam(sys_get_temp_dir(), 'office-preflight-env-');
        self::assertNotFalse($file);
        file_put_contents($file, $contents);
        $this->temporaryEnvironmentFiles[] = $file;
        return $file;
    }
}
