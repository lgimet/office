<?php

namespace App\Services;

use PDO;

class InvoiceNumberGenerator
{
    public function next(PDO $pdo, \DateTimeInterface $date): string
    {
        $year = (int)$date->format('Y');
        $pdo->prepare('INSERT INTO invoice_number_sequences (year,last_number) VALUES (?,0) ON DUPLICATE KEY UPDATE year=VALUES(year)')->execute([$year]);
        $s = $pdo->prepare('SELECT last_number FROM invoice_number_sequences WHERE year=? FOR UPDATE');
        $s->execute([$year]);
        $number = (int)$s->fetchColumn() + 1;
        $pdo->prepare('UPDATE invoice_number_sequences SET last_number=?,updated_at=CURRENT_TIMESTAMP WHERE year=?')->execute([$number,$year]);
        return sprintf('DEV-%d-%04d', $year, $number);
    }
}
