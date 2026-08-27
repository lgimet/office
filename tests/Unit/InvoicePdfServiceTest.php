<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CompanySettingsRepository;
use App\Services\InvoicePdfService;
use App\Services\InvoiceTemplateResolver;
use App\Services\TenantContext;
use PHPUnit\Framework\TestCase;

final class InvoicePdfServiceTest extends TestCase
{
    public function testIssuedInvoiceProducesPdfFromPersistedSnapshots(): void
    {
        $pdf = (new InvoicePdfService($this->resolver()))->render($this->invoice(), [[
            'position' => 1, 'label' => 'Conseil & audit', 'description' => "Ligne\navec accent é",
            'quantity' => '2', 'unit' => 'heure', 'unit_price_excl_tax' => '100.00', 'tax_rate' => '20.00',
            'line_subtotal_excl_tax' => '200.00', 'line_discount_excl_tax' => '0.00', 'discount_note' => null,
        ]]);

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }

    public function testDraftCannotBeExported(): void
    {
        $invoice = $this->invoice();
        $invoice['status'] = 'draft';

        $this->expectExceptionMessage('émises ou annulées');
        (new InvoicePdfService($this->resolver()))->render($invoice, []);
    }

    private function resolver(): InvoiceTemplateResolver
    {
        $context = (new \ReflectionClass(TenantContext::class))->newInstanceWithoutConstructor();
        $settings = $this->createMock(CompanySettingsRepository::class);
        return new InvoiceTemplateResolver($context, $settings, null, dirname(__DIR__, 2) . '/resources/pdf/invoices/default');
    }

    private function invoice(): array
    {
        return [
            'id' => 10, 'status' => 'issued', 'invoice_number' => 'F2026-0001', 'issue_date' => '2026-08-27',
            'due_date' => '2026-09-11', 'currency' => 'EUR', 'payment_terms' => '15 jours', 'payment_method' => 'Virement',
            'public_note' => 'Merci pour votre confiance', 'internal_note' => 'Ne pas afficher',
            'client_name' => 'Client & Fils', 'client_contact_name' => 'Jean Dupont', 'client_email' => 'client@example.test',
            'client_phone' => '+33 1', 'client_address_line1' => '1 rue de l’Été', 'client_address_line2' => null,
            'client_postal_code' => '75001', 'client_city' => 'Paris', 'client_country' => 'France',
            'client_siret' => '12345678901234', 'client_vat_number' => 'FR123',
            'issuer_legal_name' => 'DevSys SAS', 'issuer_trading_name' => 'DevSys', 'issuer_legal_form' => 'SAS',
            'issuer_share_capital' => null, 'issuer_address_line1' => '2 rue A', 'issuer_address_line2' => null,
            'issuer_postal_code' => '69001', 'issuer_city' => 'Lyon', 'issuer_country' => 'France',
            'issuer_email' => 'billing@example.test', 'issuer_phone' => null, 'issuer_website' => null,
            'issuer_siret' => '98765432101234', 'issuer_siren' => '987654321', 'issuer_vat_number' => 'FR987',
            'issuer_ape_code' => null, 'issuer_rcs_city' => null, 'issuer_bank_name' => null, 'issuer_iban' => null,
            'issuer_bic' => null, 'issuer_invoice_footer' => 'DevSys — Merci', 'pdf_template_source' => 'system',
            'pdf_template_version' => 'v1', 'subtotal_excl_tax' => '200.00', 'discount_total_excl_tax' => '0.00',
            'tax_total' => '40.00', 'total_incl_tax' => '240.00', 'issued_at' => '2026-08-27 10:00:00',
        ];
    }
}
