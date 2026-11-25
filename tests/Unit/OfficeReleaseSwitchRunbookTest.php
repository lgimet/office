<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OfficeReleaseSwitchRunbookTest extends TestCase
{
    public function testRunbookRequiresFpmConfigValidationAndApprovedGracefulReloadAfterSwitch(): void
    {
        $runbook = file_get_contents(__DIR__ . '/../../docs/operations/office-release-switch.md');
        self::assertIsString($runbook);

        $switch = strpos($runbook, 'remplacer atomiquement le pointeur');
        $apacheValidation = strpos($runbook, '`apachectl configtest`');
        $fpmValidation = strpos($runbook, '`php-fpm8.4 -t`');
        $fpmApproval = strpos($runbook, '**Point d’approbation PHP-FPM :**');
        $fpmReload = strpos($runbook, '`systemctl reload php8.4-fpm`');
        $fpmHealth = strpos($runbook, '`systemctl is-active php8.4-fpm`');

        self::assertNotFalse($switch);
        self::assertNotFalse($apacheValidation);
        self::assertNotFalse($fpmValidation);
        self::assertNotFalse($fpmApproval);
        self::assertNotFalse($fpmReload);
        self::assertNotFalse($fpmHealth);
        self::assertTrue($switch < $apacheValidation);
        self::assertTrue($apacheValidation < $fpmValidation);
        self::assertTrue($fpmValidation < $fpmApproval);
        self::assertTrue($fpmApproval < $fpmReload);
        self::assertTrue($fpmReload < $fpmHealth);
        self::assertStringContainsString('tous les pools', $runbook);
        self::assertStringContainsString('Un reload FPM après rollback', $runbook);
        self::assertStringNotContainsString('systemctl restart php8.4-fpm', $runbook);
    }
}
