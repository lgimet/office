<?php

namespace Tests\Unit;

use App\Repositories\CompanySettingsRepository;
use App\Repositories\InvoiceRepository;
use App\Services\CompanySettingsService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceService;
use PHPUnit\Framework\TestCase;

final class InvoiceServiceViewTest extends TestCase
{
    public function testViewReturnsIssuedInvoiceAndPersistedLines(): void
    {
        $repository = $this->createMock(InvoiceRepository::class);
        $invoice = ['id' => 50, 'status' => 'issued', 'invoice_number' => 'F2026-0001'];
        $lines = [['label' => 'Conseil', 'line_total_incl_tax' => '120.00']];
        $repository->expects(self::once())->method('find')->with(50)->willReturn($invoice);
        $repository->expects(self::once())->method('lines')->with(50)->willReturn($lines);

        $service = new InvoiceService(
            $repository,
            new InvoiceCalculationService(),
            $this->createMock(CompanySettingsRepository::class),
            $this->createMock(CompanySettingsService::class),
        );

        self::assertSame(['invoice' => $invoice, 'lines' => $lines], $service->view(50));
    }

    public function testViewRejectsMissingInvoice(): void
    {
        $repository = $this->createMock(InvoiceRepository::class);
        $repository->method('find')->with(50)->willReturn(null);
        $service = $this->service($repository);

        $this->expectException(\InvalidArgumentException::class);
        $service->view(50);
    }

    public function testViewDoesNotTurnDraftIntoReadableIssuedDocument(): void
    {
        $repository = $this->createMock(InvoiceRepository::class);
        $repository->method('find')->with(50)->willReturn(['id' => 50, 'status' => 'draft']);
        $service = $this->service($repository);

        $this->expectException(\LogicException::class);
        $service->view(50);
    }

    private function service(InvoiceRepository $repository): InvoiceService
    {
        return new InvoiceService(
            $repository,
            new InvoiceCalculationService(),
            $this->createMock(CompanySettingsRepository::class),
            $this->createMock(CompanySettingsService::class),
        );
    }
}
