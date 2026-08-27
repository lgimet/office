<?php

declare(strict_types=1);

namespace App\Services;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class InvoicePdfService
{
    private Environment $twig;

    public function __construct(private readonly InvoiceTemplateResolver $templates)
    {
        $this->twig = new Environment(new FilesystemLoader(dirname(__DIR__) . '/Views'));
    }

    /** @param array<string, mixed> $invoice @param array<int, array<string, mixed>> $lines */
    public function render(array $invoice, array $lines): string
    {
        if (!in_array($invoice['status'] ?? null, ['issued', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Seules les factures émises ou annulées peuvent être exportées en PDF.');
        }
        if (trim((string) ($invoice['invoice_number'] ?? '')) === '') {
            throw new \InvalidArgumentException('La facture ne possède pas de numéro définitif.');
        }

        $template = $this->templates->resolveForInvoice($invoice);
        $html = $this->twig->render('Invoices/pdf.twig', [
            'invoice' => $invoice,
            'lines' => $lines,
            'template' => $template,
        ]);
        $tempDir = trim((string) ($_ENV['OFFICE_PDF_TEMP_DIR'] ?? '')) ?: sys_get_temp_dir() . '/office-mpdf';
        if (!is_dir($tempDir) && !mkdir($tempDir, 0770, true) && !is_dir($tempDir)) {
            throw new \RuntimeException('Le répertoire temporaire PDF est indisponible.');
        }

        $mpdf = new InvoicePdfDocument(['format' => 'A4', 'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 18, 'margin_bottom' => 18, 'tempDir' => $tempDir]);
        $mpdf->setInvoiceTemplates($template['first_page'], $template['continuation']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}

final class InvoicePdfDocument extends \Mpdf\Mpdf
{
    private string $firstPageTemplate = '';
    private string $continuationTemplate = '';

    public function setInvoiceTemplates(string $firstPage, string $continuation): void
    {
        $this->firstPageTemplate = $firstPage;
        $this->continuationTemplate = $continuation;
    }

    public function Header($content = '')
    {
        $template = $this->page === 1 ? $this->firstPageTemplate : $this->continuationTemplate;
        if ($template === '') return;
        $this->setSourceFile($template);
        $this->useTemplate($this->importPage(1));
    }
}
