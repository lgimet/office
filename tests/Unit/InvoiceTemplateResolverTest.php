<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CompanySettingsRepository;
use App\Services\InvoiceTemplateResolver;
use App\Services\TenantContext;
use PHPUnit\Framework\TestCase;

final class InvoiceTemplateResolverTest extends TestCase
{
    public function testNullVersionUsesSystemV1AndContinuationFallback(): void
    {
        $root = $this->root('system');
        mkdir($root . '/v1');
        file_put_contents($root . '/v1/first-page.pdf', '%PDF');
        $descriptor = $this->resolver(['invoice_template_version' => null], $root, $root)->resolveCurrent();

        self::assertSame('system', $descriptor['source']);
        self::assertSame('v1', $descriptor['version']);
        self::assertSame($root . '/v1/first-page.pdf', $descriptor['continuation']);
    }

    public function testTenantVersionUsesSafeStorageKey(): void
    {
        $storage = $this->root('storage/tenants/key-1/invoices/templates/v2');
        file_put_contents($storage . '/first-page.pdf', '%PDF');
        $descriptor = $this->resolver(['invoice_template_version' => 'v2'], dirname($storage, 5), sys_get_temp_dir(), ['uuid' => 'tenant-uuid', 'storage_key' => 'key-1'])->resolveCurrent();

        self::assertSame('tenant', $descriptor['source']);
        self::assertSame('v2', $descriptor['version']);
        self::assertSame($storage . '/first-page.pdf', $descriptor['first_page']);
    }

    public function testIncompleteConfiguredTenantModelFailsWithoutSystemFallback(): void
    {
        $storage = $this->root('storage');
        $resolver = $this->resolver(['invoice_template_version' => 'v2'], $storage, sys_get_temp_dir(), ['uuid' => 'tenant-uuid']);

        $this->expectExceptionMessage('modèle de facture configuré');
        $resolver->resolveCurrent();
    }

    public function testHistoricalVersionIgnoresCurrentConfiguration(): void
    {
        $root = $this->root('system');
        mkdir($root . '/v1');
        file_put_contents($root . '/v1/first-page.pdf', '%PDF');
        $descriptor = $this->resolver(['invoice_template_version' => 'v3'], $root, $root)->resolveForInvoice(['pdf_template_source' => 'system', 'pdf_template_version' => 'v1']);

        self::assertSame('v1', $descriptor['version']);
    }

    public function testUnsafeStorageKeyIsRejected(): void
    {
        $resolver = $this->resolver(['invoice_template_version' => 'v1'], sys_get_temp_dir(), sys_get_temp_dir(), ['uuid' => 'tenant-uuid', 'storage_key' => '../escape']);

        $this->expectExceptionMessage('clé de stockage');
        $resolver->resolveCurrent();
    }

    private function resolver(array $settings, string $storageRoot, string $systemRoot, array $tenant = []): InvoiceTemplateResolver
    {
        $repository = $this->createMock(CompanySettingsRepository::class);
        $repository->method('find')->willReturn($settings);
        $context = (new \ReflectionClass(TenantContext::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(TenantContext::class, 'resolved'))->setValue($context, true);
        (new \ReflectionProperty(TenantContext::class, 'tenant'))->setValue($context, $tenant + ['id' => 1, 'uuid' => 'tenant-uuid']);

        return new InvoiceTemplateResolver($context, $repository, $storageRoot, $systemRoot);
    }

    private function root(string $path): string
    {
        $root = sys_get_temp_dir() . '/invoice-template-' . uniqid('', true) . '/' . trim($path, '/');
        mkdir($root, 0777, true);
        return $root;
    }
}
