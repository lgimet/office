<?php

declare(strict_types=1);

namespace App\Services;

use Devsys\Shared\Api\Devsys\Invoices\Dto\{CreateInvoiceDraftRequest, InvoiceCommandResult, InvoiceDetails, InvoiceDraftUpdateResult, UpdateInvoiceDraftRequest};

/** Narrow test seam over the shared SDK; production always delegates to InvoicesApi. */
interface InvoiceDraftApiInterface
{
    public function get(int $invoiceId): InvoiceDetails;
    public function recipePreflight(): void;
    public function createDraft(CreateInvoiceDraftRequest $request, string $idempotencyKey): InvoiceCommandResult;
    public function updateDraft(int $invoiceId, UpdateInvoiceDraftRequest $request, string $idempotencyKey, string $etag): InvoiceDraftUpdateResult;
    public function issue(int $invoiceId, string $idempotencyKey, string $sourceApp): InvoiceCommandResult;
}
