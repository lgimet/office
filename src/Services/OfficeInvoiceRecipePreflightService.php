<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Oidc\OidcSessionService;

/** Temporary operator-only bridge to the API's non-mutating preflight GET. */
final class OfficeInvoiceRecipePreflightService
{
    public function __construct(
        private readonly InvoiceDraftApiInterface $invoices,
        private readonly OidcSessionService $sessions,
    ) {
    }

    public function isEnabledForCurrentOperator(): bool
    {
        if (($_ENV['OFFICE_INVOICE_PREFLIGHT_ENABLED'] ?? null) !== 'true') {
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
}
