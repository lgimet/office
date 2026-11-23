<?php

declare(strict_types=1);

namespace App\Services;

use Devsys\Shared\Api\Devsys\Invoices\Dto\{CreateInvoiceDraftRequest, InvoiceCommandResult, InvoiceDetails, InvoiceDraftUpdateResult, UpdateInvoiceDraftRequest};
use Devsys\Shared\Api\Devsys\Invoices\InvoicesApi;

final class SharedInvoiceDraftApi implements InvoiceDraftApiInterface
{
    public function __construct(private readonly InvoicesApi $invoices)
    {
    }

    public function recipePreflight(): void
    {
        $this->invoices->recipePreflight();
    }

    public function get(int $invoiceId): InvoiceDetails
    {
        try {
            return $this->invoices->get($invoiceId);
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException('The shared invoice detail response is invalid.', 0, $exception);
        }
    }

    public function createDraft(CreateInvoiceDraftRequest $request, string $idempotencyKey): InvoiceCommandResult
    {
        try {
            return $this->invoices->createDraft($request, $idempotencyKey);
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException('The shared invoice creation response is invalid.', 0, $exception);
        }
    }

    public function updateDraft(int $invoiceId, UpdateInvoiceDraftRequest $request, string $idempotencyKey, string $etag): InvoiceDraftUpdateResult
    {
        try {
            return $this->invoices->updateDraft($invoiceId, $request, $idempotencyKey, $etag);
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException('The shared invoice update response is invalid.', 0, $exception);
        }
    }

    public function issue(int $invoiceId, string $idempotencyKey, string $sourceApp): InvoiceCommandResult
    {
        try {
            return $this->invoices->issue($invoiceId, $idempotencyKey, $sourceApp);
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException('The shared invoice issue response is invalid.', 0, $exception);
        }
    }
}
