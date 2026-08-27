<?php

namespace App\Repositories;

use App\Core\BaseRepository;

final class TenantRepository extends BaseRepository
{
    public function findForPrincipal(string $tenantUuid, string $principalUuid): ?array
    {
        return $this->query(
            'SELECT t.id, t.uuid, t.name, t.slug, t.status, tm.role,
                    COALESCE(cs.invoice_number_prefix, \'F\') AS invoice_number_prefix,
                    t.storage_strategy, t.storage_key, t.storage_state
             FROM tenants t
             INNER JOIN tenant_memberships tm ON tm.tenant_id = t.id
             INNER JOIN users u ON u.id = tm.user_id
             LEFT JOIN company_settings cs ON cs.tenant_id = t.id
             WHERE t.uuid = ? AND u.uuid = ? AND t.status = \'active\'
               AND tm.status = \'active\' AND u.status = \'active\'
             LIMIT 1',
            [$tenantUuid, $principalUuid]
        )?->fetch() ?: null;
    }
}
