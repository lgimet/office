<?php

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\TenantRepository;
use App\Services\Oidc\OidcSessionService;

final class TenantContext
{
    private ?array $tenant = null;
    private bool $resolved = false;

    public function __construct(
        private readonly OidcSessionService $sessions,
        private readonly TenantRepository $tenants,
    ) {
    }

    public function id(): int
    {
        if (!$this->resolved) {
            $this->resolve();
        }

        if ($this->tenant === null) {
            throw new HttpException(403, 'Aucun tenant actif n’est associé à cette identité.');
        }

        return (int) $this->tenant['id'];
    }

    public function tenant(): array
    {
        $this->id();

        return $this->tenant;
    }

    private function resolve(): void
    {
        $this->resolved = true;
        $identity = $this->sessions->identity();
        if ($identity === null) {
            return;
        }

        $tenantUuid = (string) ($identity['tenant_uuid'] ?? '');
        $principalUuid = (string) ($identity['user_uuid'] ?? '');
        if ($tenantUuid === '' || $principalUuid === '') {
            return;
        }

        $this->tenant = $this->tenants->findForPrincipal($tenantUuid, $principalUuid);
    }
}
