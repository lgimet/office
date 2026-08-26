<?php

namespace Tests\Unit;

use App\Services\InvoiceNumberGenerator;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class InvoiceNumberGeneratorTest extends TestCase
{
    public function testNumberUsesTheFinalDateYear(): void
    {
        $pdo = $this->sequencePdo(2027, 0);

        self::assertSame(
            'D2027-0001',
            (new InvoiceNumberGenerator())->next($pdo, 7, new \DateTimeImmutable('2027-01-02'), 'D')
        );
    }

    public function testNumberKeepsTheYearWhenDateChangesWithinYear(): void
    {
        $pdo = $this->sequencePdo(2026, 41);

        self::assertSame(
            'F2026-0042',
            (new InvoiceNumberGenerator())->next($pdo, 7, new \DateTimeImmutable('2026-11-20'), 'F')
        );
    }

    private function sequencePdo(int $year, int $lastNumber): PDO
    {
        $insert = $this->createMock(PDOStatement::class);
        $insert->expects(self::once())->method('execute')->with([7, $year]);

        $select = $this->createMock(PDOStatement::class);
        $select->expects(self::once())->method('execute')->with([7, $year]);
        $select->expects(self::once())->method('fetchColumn')->willReturn($lastNumber);

        $update = $this->createMock(PDOStatement::class);
        $update->expects(self::once())->method('execute')->with([$lastNumber + 1, 7, $year]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::exactly(3))->method('prepare')->willReturnOnConsecutiveCalls($insert, $select, $update);

        return $pdo;
    }
}
