<?php

namespace App\Services;

use PDO;

class InvoiceNumberGenerator
{
    public function next(PDO $pdo, int $tenantId, \DateTimeInterface $date, string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        if (!preg_match('/^[A-Z0-9]{1,8}$/', $prefix)) {
            throw new \InvalidArgumentException('Le préfixe des factures est invalide.');
        }

        $year = (int)$date->format('Y');
        $pdo->prepare('INSERT INTO invoice_number_sequences (tenant_id,year,last_number) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id), year=VALUES(year)')->execute([$tenantId, $year]);
        $s = $pdo->prepare('SELECT last_number FROM invoice_number_sequences WHERE tenant_id=? AND year=? FOR UPDATE');
        $s->execute([$tenantId, $year]);
        $number = (int)$s->fetchColumn() + 1;
        $pdo->prepare('UPDATE invoice_number_sequences SET last_number=?,updated_at=CURRENT_TIMESTAMP WHERE tenant_id=? AND year=?')->execute([$number, $tenantId, $year]);
        return sprintf('%s%d-%04d', $prefix, $year, $number);
    }
}
