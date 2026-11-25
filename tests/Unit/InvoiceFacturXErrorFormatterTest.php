<?php

namespace App\Tests\Unit;

use App\Services\InvoiceFacturXErrorFormatter;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiValidationException;
use PHPUnit\Framework\TestCase;

final class InvoiceFacturXErrorFormatterTest extends TestCase
{
    public function testFormatsOnlyWhitelistedMissingFieldCodesWithoutValues(): void
    {
        $exception = new DevsysApiValidationException(
            'The snapshot is incomplete.',
            422,
            'invoices/12/facturx',
            null,
            ['missing_fields' => ['client_email', 'issuer_invoice_note_PMT', 'line_2.tax_category_code']],
            'FACTURX_SNAPSHOT_INCOMPLETE',
        );

        $message = (new InvoiceFacturXErrorFormatter())->snapshotIncomplete($exception);

        self::assertStringContainsString('coordonnées du client', $message);
        self::assertStringContainsString('mentions légales de l’émetteur', $message);
        self::assertStringContainsString('données des lignes', $message);
        self::assertStringContainsString('Elle reste inchangée.', $message);
        self::assertStringNotContainsString('client_email', $message);
        self::assertStringNotContainsString('facturx', $message);
    }

    public function testMalformedOrUnexpectedDetailsUseSafeGenericMessage(): void
    {
        $formatter = new InvoiceFacturXErrorFormatter();
        $generic = 'La facture émise ne peut pas être exportée en Factur-X à partir de ses données figées. Elle reste inchangée. Contactez l’administrateur de facturation.';

        foreach ([
            ['missing_fields' => 'client_email'],
            ['missing_fields' => ['client_email', 'sensitive value']],
            ['missing_fields' => []],
        ] as $details) {
            $exception = new DevsysApiValidationException('ignored', 422, null, null, $details, 'FACTURX_SNAPSHOT_INCOMPLETE');
            self::assertSame($generic, $formatter->snapshotIncomplete($exception));
        }

        $wrongCode = new DevsysApiValidationException('ignored', 422, null, null, ['missing_fields' => ['client_email']], 'OTHER_ERROR');
        self::assertSame($generic, $formatter->snapshotIncomplete($wrongCode));
    }

    public function testPreIssueDetailsAreActionableWithoutExposingFieldValues(): void
    {
        $exception = new DevsysApiValidationException(
            'ignored', 422, null, null,
            ['missing_fields' => ['issuer_email', 'issuer_invoice_note_PMT', 'line_1.tax_category_rate']],
            'INVOICE_FACTURX_PREISSUE_INCOMPLETE',
        );
        $message = (new InvoiceFacturXErrorFormatter())->preIssueIncomplete($exception);
        self::assertStringContainsString('identification ou coordonnées de l’émetteur', $message);
        self::assertStringContainsString('mentions légales de l’émetteur', $message);
        self::assertStringContainsString('données des lignes', $message);
        self::assertStringContainsString('aucun numéro n’a été attribué', mb_strtolower($message));
        self::assertStringNotContainsString('issuer_email', $message);
        self::assertStringNotContainsString('billing@example.test', $message);

        $malformed = new DevsysApiValidationException('ignored', 422, null, null, ['missing_fields' => ['unknown_secret']], 'INVOICE_FACTURX_PREISSUE_INCOMPLETE');
        self::assertStringContainsString('aucun numéro n’a été attribué', mb_strtolower((new InvoiceFacturXErrorFormatter())->preIssueIncomplete($malformed)));
        self::assertStringNotContainsString('unknown_secret', (new InvoiceFacturXErrorFormatter())->preIssueIncomplete($malformed));
    }
}
