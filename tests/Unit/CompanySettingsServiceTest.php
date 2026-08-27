<?php

namespace Tests\Unit;

use App\Repositories\CompanySettingsRepository;
use App\Services\CompanySettingsService;
use PHPUnit\Framework\TestCase;

final class CompanySettingsServiceTest extends TestCase
{
    public function testSaveNormalizesSettingsAsNamedFieldsAndIgnoresForgedTenant(): void
    {
        $repository = $this->createMock(CompanySettingsRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (array $settings): bool {
                return ($settings['legal_name'] ?? null) === 'DevSys'
                    && ($settings['invoice_number_prefix'] ?? null) === 'D'
                    && ($settings['default_payment_terms_code'] ?? null) === 'days_15'
                    && !array_key_exists('tenant_id', $settings);
            }));

        (new CompanySettingsService($repository))->save([
            'tenant_id' => 999,
            'legal_name' => ' DevSys ',
            'email' => 'contact@devsys.fr',
            'default_currency' => 'eur',
            'default_tax_rate' => '20',
            'invoice_number_prefix' => 'd',
            'default_payment_terms' => 'Sous 15 jours',
        ]);
    }

    public function testInvoiceIssuerSnapshotCopiesOnlyHistoricalIssuerFields(): void
    {
        $snapshot = (new CompanySettingsService($this->createMock(CompanySettingsRepository::class)))->invoiceIssuerSnapshot([
            'legal_name' => 'DevSys', 'address_line1' => '1 rue A', 'iban' => 'FR00', 'invoice_footer' => 'Merci',
            'default_currency' => 'USD', 'tenant_id' => 999,
        ]);

        self::assertSame('DevSys', $snapshot['legal_name']);
        self::assertSame('1 rue A', $snapshot['address_line1']);
        self::assertSame('FR00', $snapshot['iban']);
        self::assertSame('Merci', $snapshot['invoice_footer']);
        self::assertArrayNotHasKey('default_currency', $snapshot);
        self::assertArrayNotHasKey('tenant_id', $snapshot);
    }

    public function testSavePreservesExistingTemplateVersionAndIgnoresForgedInput(): void
    {
        $repository = $this->createMock(CompanySettingsRepository::class);
        $repository->method('find')->willReturn(['invoice_template_version' => 'v3']);
        $repository->expects(self::once())->method('save')->with(self::callback(static fn (array $settings): bool => ($settings['invoice_template_version'] ?? null) === 'v3'));

        (new CompanySettingsService($repository))->save([
            'legal_name' => 'DevSys', 'invoice_template_version' => 'v999',
        ]);
    }
}
