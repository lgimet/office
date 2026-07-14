<?php

namespace App\Services;

use App\Repositories\Client as RepositoriesClient;

class Client
{
    private RepositoriesClient $repository;

    public function __construct(RepositoriesClient $repository)
    {
        $this->repository = $repository;
    }

    public function getFormData(): array
    {
        $client = $this->repository->findFirst();

        if ($client === null) {
            return [
                'client' => null,
                'modules_json' => '[]',
            ];
        }

        $modules = $client['modules'] ?? '[]';

        return [
            'client' => $client,
            'modules_json' => is_string($modules) ? $modules : json_encode($modules),
        ];
    }

    public function getOptions(string $type = 'countries'): array
    {
        return match ($type) {
            'activities' => [
                ['id' => 'crm', 'label' => 'CRM'],
                ['id' => 'billing', 'label' => 'Facturation'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'support', 'label' => 'Support client'],
            ],
            default => [
                ['id' => 'fr', 'label' => 'France'],
                ['id' => 'be', 'label' => 'Belgique'],
                ['id' => 'ch', 'label' => 'Suisse'],
                ['id' => 'ca', 'label' => 'Canada'],
            ],
        };
    }

    public function updateClient(array $data): void
    {
        $normalized = [
            'company' => trim((string) ($data['company'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'country' => $data['country'] ?? null,
            'modules' => $this->normalizeModules($data['modules'] ?? '[]'),
            'notifications' => !empty($data['notifications']) ? 1 : 0,
            'iban' => trim((string) ($data['iban'] ?? '')),
            'bic' => trim((string) ($data['bic'] ?? '')),
        ];

        $existing = $this->repository->findFirst();

        if ($existing !== null) {
            $this->repository->update((int) $existing['id'], $normalized);
            return;
        }

        $this->repository->create($normalized);
    }

    private function normalizeModules(mixed $modules): string
    {
        if (is_array($modules)) {
            return json_encode(array_values($modules), JSON_UNESCAPED_UNICODE);
        }

        if (is_string($modules) && $modules !== '') {
            json_decode($modules, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $modules;
            }
        }

        return '[]';
    }
}
