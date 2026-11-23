<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Oidc\OidcSessionService;

/** Temporary operator-only bridge to the API's non-mutating preflight GET. */
final class OfficeInvoiceRecipePreflightService
{
    private const MAX_LIFETIME_SECONDS = 300;

    private readonly \Closure $clock;
    private readonly \Closure $bootIdProvider;
    private readonly string $environmentFile;

    public function __construct(
        private readonly InvoiceDraftApiInterface $invoices,
        private readonly OidcSessionService $sessions,
        ?callable $clock = null,
        ?string $environmentFile = null,
        ?callable $bootIdProvider = null,
    ) {
        $this->clock = $clock === null ? static fn (): int => time() : \Closure::fromCallable($clock);
        $this->environmentFile = $environmentFile ?? dirname(__DIR__, 2).'/.env';
        $this->bootIdProvider = $bootIdProvider === null ? static function (): ?string {
            $bootId = @file_get_contents('/proc/sys/kernel/random/boot_id');
            return is_string($bootId) ? trim($bootId) : null;
        } : \Closure::fromCallable($bootIdProvider);
    }

    public function isEnabledForCurrentOperator(): bool
    {
        if (($_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] ?? null) !== 'true') {
            return false;
        }

        if (!$this->deadlineIsOpen()) {
            return false;
        }

        $expectedSubject = $_ENV['OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT'] ?? null;
        $identity = $this->sessions->identity();
        $actualSubject = is_array($identity) ? ($identity['sub'] ?? null) : null;
        if (!is_string($expectedSubject) || !self::validSubject($expectedSubject)
            || !is_string($actualSubject) || !self::validSubject($actualSubject)
            || !hash_equals(strtolower($expectedSubject), strtolower($actualSubject))) {
            return false;
        }

        return true;
    }

    /** Returns only a redacted outcome; API response details are never forwarded. */
    public function check(): string
    {
        if (!$this->isEnabledForCurrentOperator()) {
            return 'UNAVAILABLE';
        }

        try {
            $this->invoices->recipePreflight();
            return 'PASS';
        } catch (\Devsys\Shared\Api\Devsys\Exception\DevsysApiAuthenticationException) {
            return 'REFUSED';
        } catch (\Throwable) {
            return 'UNAVAILABLE';
        }
    }

    private static function validSubject(string $subject): bool
    {
        return preg_match('/^user:[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $subject) === 1;
    }

    private function deadlineIsOpen(): bool
    {
        $startedAt = self::parseUnixSeconds($_ENV['OFFICE_INVOICE_PREFLIGHT_STARTED_AT'] ?? null);
        $expiresAt = self::parseUnixSeconds($_ENV['OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT'] ?? null);
        $expectedBootId = $_ENV['OFFICE_INVOICE_PREFLIGHT_BOOT_ID'] ?? null;
        if ($startedAt === null || $expiresAt === null || $expiresAt <= $startedAt
            || !is_string($expectedBootId) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $expectedBootId) !== 1
            || ($expiresAt - $startedAt) > self::MAX_LIFETIME_SECONDS
            || self::hasAmbiguousDotenvAssignments($this->environmentFile)) {
            return false;
        }

        try {
            $now = ($this->clock)();
            $currentBootId = ($this->bootIdProvider)();
        } catch (\Throwable) {
            return false;
        }

        return is_int($now)
            && is_string($currentBootId)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $currentBootId) === 1
            && hash_equals(strtolower($expectedBootId), strtolower($currentBootId))
            && $now >= $startedAt
            && $now < $expiresAt;
    }

    private static function parseUnixSeconds(mixed $value): ?int
    {
        // Canonical unsigned Unix seconds only: no whitespace, sign, float,
        // leading zero, duplicated-value encoding, or millisecond timestamp.
        if (!is_string($value) || preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) !== 1) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX]]);
        return is_int($parsed) ? $parsed : null;
    }

    private static function hasAmbiguousDotenvAssignments(string $environmentFile): bool
    {
        $contents = @file_get_contents($environmentFile);
        if (!is_string($contents)) {
            return true;
        }

        $watched = [
            'OFFICE_INVOICE_PREFLIGHT_ENABLED',
            'OFFICE_INVOICE_PREFLIGHT_OPERATOR_SUBJECT',
            'OFFICE_INVOICE_PREFLIGHT_STARTED_AT',
            'OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT',
            'OFFICE_INVOICE_PREFLIGHT_BOOT_ID',
        ];
        $counts = array_fill_keys($watched, 0);
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches) === 1 && isset($counts[$matches[1]])) {
                ++$counts[$matches[1]];
                if ($counts[$matches[1]] > 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
