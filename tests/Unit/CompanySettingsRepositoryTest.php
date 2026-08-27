<?php

namespace Tests\Unit;

use App\Repositories\CompanySettingsRepository;
use App\Services\TenantContext;
use PHPUnit\Framework\TestCase;

final class CompanySettingsRepositoryTest extends TestCase
{
    public function testSaveUsesCurrentTenantInNamedParametersAndIgnoresForgedTenant(): void
    {
        $tenant = (new \ReflectionClass(TenantContext::class))->newInstanceWithoutConstructor();
        $resolved = new \ReflectionProperty(TenantContext::class, 'resolved');
        $resolved->setValue($tenant, true);
        $tenantData = new \ReflectionProperty(TenantContext::class, 'tenant');
        $tenantData->setValue($tenant, ['id' => 12]);
        $repository = new CapturingCompanySettingsRepository();
        $property = new \ReflectionProperty(CompanySettingsRepository::class, 'tenant');
        $property->setValue($repository, $tenant);

        $repository->save([
            'tenant_id' => 999,
            'legal_name' => 'DevSys',
            'default_currency' => 'EUR',
        ]);

        self::assertSame(12, $repository->parameters['tenant_id']);
        self::assertSame('DevSys', $repository->parameters['legal_name']);
        self::assertArrayNotHasKey('999', $repository->parameters);
        self::assertStringContainsString(':tenant_id', $repository->sql);
        self::assertStringContainsString(':invoice_footer', $repository->sql);
    }
}

final class CapturingCompanySettingsRepository extends CompanySettingsRepository
{
    public string $sql = '';
    public array $parameters = [];

    public function __construct()
    {
    }

    protected function query(string $sql, array $params = []): ?\PDOStatement
    {
        $this->sql = $sql;
        $this->parameters = $params;

        return null;
    }
}
