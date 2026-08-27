<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CompanySettingsRepository;

final class InvoiceTemplateResolver
{
    public const SYSTEM_VERSION = 'v1';

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanySettingsRepository $settings,
        private readonly ?string $storageRoot = null,
        private readonly ?string $systemRoot = null,
    ) {
    }

    /** @return array{source: string, version: string, first_page: string, continuation: string} */
    public function resolveCurrent(): array
    {
        $settings = $this->settings->find() ?? [];
        $version = $settings['invoice_template_version'] ?? null;
        if ($version === null || trim((string) $version) === '') {
            return $this->descriptor('system', self::SYSTEM_VERSION);
        }

        $this->assertVersion((string) $version);
        return $this->descriptor('tenant', (string) $version);
    }

    /** @param array<string, mixed> $invoice */
    public function resolveForInvoice(array $invoice): array
    {
        $source = trim((string) ($invoice['pdf_template_source'] ?? ''));
        $version = trim((string) ($invoice['pdf_template_version'] ?? ''));
        if (!in_array($source, ['system', 'tenant'], true) || $version === '') {
            throw new \InvalidArgumentException('Le modèle PDF historique de cette facture est invalide.');
        }

        $this->assertVersion($version);
        return $this->descriptor($source, $version);
    }

    /** @return array{source: string, version: string, first_page: string, continuation: string} */
    private function descriptor(string $source, string $version): array
    {
        $base = $source === 'system'
            ? ($this->systemRoot ?? dirname(__DIR__, 2) . '/resources/pdf/invoices/default')
            : ($this->storageRoot ?? ($_ENV['OFFICE_STORAGE_DIR'] ?? dirname(__DIR__, 2) . '/storage'))
                . '/tenants/' . $this->storageKey() . '/invoices/templates';
        $directory = rtrim($base, '/') . '/' . $version;
        $firstPage = $directory . '/first-page.pdf';
        $continuation = $directory . '/continuation.pdf';

        if (!$this->usableFile($firstPage)) {
            throw new \RuntimeException(
                $source === 'tenant'
                    ? 'Le modèle de facture configuré pour votre société est incomplet.'
                    : 'Le modèle système de facture est indisponible.'
            );
        }

        if (!$this->usableFile($continuation)) {
            $continuation = $firstPage;
        }

        return [
            'source' => $source,
            'version' => $version,
            'first_page' => $firstPage,
            'continuation' => $continuation,
        ];
    }

    private function storageKey(): string
    {
        $tenant = $this->tenant->tenant();
        $key = trim((string) ($tenant['storage_key'] ?? ''));
        if ($key === '') {
            $key = trim((string) ($tenant['uuid'] ?? ''));
        }
        if ($key === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $key) || str_contains($key, '..')) {
            throw new \RuntimeException('La clé de stockage du tenant est invalide.');
        }

        return $key;
    }

    private function assertVersion(string $version): void
    {
        if (!preg_match('/^v[1-9][0-9]*$/', $version)) {
            throw new \InvalidArgumentException('La version du modèle de facture est invalide.');
        }
    }

    private function usableFile(string $path): bool
    {
        return is_file($path) && is_readable($path) && (int) filesize($path) > 0
            && strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }
}
