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
}
