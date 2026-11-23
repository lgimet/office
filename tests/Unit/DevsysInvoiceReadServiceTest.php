<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DevsysInvoiceReadService;
use Devsys\Shared\Api\Devsys\Configuration\DevsysApiConfig;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiResponseException;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;
use Devsys\Shared\Api\Devsys\Invoices\InvoicesApi;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class DevsysInvoiceReadServiceTest extends TestCase
{
    public function testMapsDraftIssuedAndCancelledItemsIntoOfficeTableRows(): void
    {
        $history = [];
        $client = $this->client($history, new Response(200, [], json_encode($this->payload([
            $this->item(41, 'draft', null, ' Brouillon ', '0.00', '0.00', '0.00'),
            $this->item(42, 'issued', 'DEV2026-0042', 'SpotCard', '150.00', '30.00', '180.00'),
            $this->item(43, 'cancelled', 'DEV2026-0043', 'MenuFlow', '15.50', '3.10', '18.60'),
        ], 4))));

        $service = new DevsysInvoiceReadService(new InvoicesApi($client));
        $result = $service->list(['page' => '2', 'limit' => '3', 'search' => ' DEV ', 'status' => 'issued']);

        self::assertSame(4, $result['pages']);
        parse_str((string) $history[0]['request']->getUri()->getQuery(), $query);
        self::assertSame(['page' => '2', 'per_page' => '3', 'search' => 'DEV', 'status' => 'issued'], $query);
        self::assertSame([
            ['id' => 41, 'reference' => 'Brouillon #41', 'invoice_number' => null, 'client_name' => ' Brouillon ', 'issue_date' => null, 'due_date' => null, 'subtotal_excl_tax' => '0.00', 'tax_total' => '0.00', 'total_incl_tax' => '0.00', 'status' => 'draft'],
            ['id' => 42, 'reference' => 'DEV2026-0042', 'invoice_number' => 'DEV2026-0042', 'client_name' => 'SpotCard', 'issue_date' => '2026-03-01', 'due_date' => '2026-03-31', 'subtotal_excl_tax' => '150.00', 'tax_total' => '30.00', 'total_incl_tax' => '180.00', 'status' => 'issued'],
            ['id' => 43, 'reference' => 'DEV2026-0043', 'invoice_number' => 'DEV2026-0043', 'client_name' => 'MenuFlow', 'issue_date' => '2026-03-01', 'due_date' => '2026-03-31', 'subtotal_excl_tax' => '15.50', 'tax_total' => '3.10', 'total_incl_tax' => '18.60', 'status' => 'cancelled'],
        ], $result['rows']);
    }

    public function testEmptyApiPagePreservesOneOfficePageAndBoundsFiltersWithoutTenant(): void
    {
        $history = [];
        $client = $this->client($history, new Response(200, [], json_encode($this->payload([], 0))));

        $result = (new DevsysInvoiceReadService(new InvoicesApi($client)))->list([
            'page' => '-4',
            'limit' => '999',
            'search' => str_repeat('x', 121),
            'status' => 'tenant-A',
            'tenant_id' => 'tenant-B',
            'tenant_uuid' => 'tenant-C',
        ]);

        self::assertSame(['rows' => [], 'pages' => 1], $result);
        parse_str((string) $history[0]['request']->getUri()->getQuery(), $query);
        self::assertSame(['page' => '1', 'per_page' => '100', 'search' => str_repeat('x', 120)], $query);
        self::assertArrayNotHasKey('tenant_id', $query);
        self::assertArrayNotHasKey('tenant_uuid', $query);
    }

    public function testSdkFailureIsNotConvertedToAnEmptyList(): void
    {
        $history = [];
        $client = $this->client($history, new Response(500, [], '{"success":false}'));

        try {
            (new DevsysInvoiceReadService(new InvoicesApi($client)))->list([]);
            self::fail('Expected API failure to propagate.');
        } catch (DevsysApiResponseException $exception) {
            self::assertSame(500, $exception->getStatusCode());
        }
    }

    public function testIssuedApiViewUsesFrozenClientAndIssuerSnapshotsAndDisablesUnsupportedPdf(): void
    {
        $invoice = [
            'id' => 84, 'invoice_number' => '2026-0008', 'status' => 'issued', 'revision' => 5,
            'business_reference' => 'DEV-TEST-84', 'issue_date' => '2026-11-23', 'due_date' => '2026-12-08', 'currency' => 'EUR',
            'client' => ['name' => 'Snapshot Client', 'contact_name' => 'Snapshot Contact', 'address_line1' => '1 rue test', 'address_line2' => null, 'postal_code' => '75000', 'city' => 'Paris', 'country' => 'FR', 'phone' => null, 'email' => null, 'siret' => '123', 'vat_number' => null, 'uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'],
            'issuer' => ['legal_name' => 'Snapshot Issuer', 'trading_name' => null, 'legal_form' => 'SAS', 'share_capital' => '1000.00', 'address_line1' => '2 rue test', 'address_line2' => null, 'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR', 'email' => null, 'phone' => null, 'website' => null, 'siret' => '456', 'siren' => '456', 'vat_number' => null, 'ape_code' => null, 'rcs_city' => 'Paris', 'bank_name' => null, 'iban' => null, 'bic' => null, 'invoice_footer' => 'Snapshot footer'],
            'amounts' => ['subtotal_excl_tax' => '10.00', 'discount_total_excl_tax' => '0.00', 'tax_total' => '2.00', 'total_incl_tax' => '12.00'],
            'lines' => [[
                'id' => 1, 'position' => 1, 'label' => 'Service', 'description' => 'Issued snapshot', 'quantity' => '1.000', 'unit' => 'unité',
                'unit_price_excl_tax' => '10.00', 'discount_type' => null, 'discount_value' => null, 'discount_note' => null, 'tax_rate' => '20.00',
                'line_subtotal_excl_tax' => '10.00', 'line_discount_excl_tax' => '0.00', 'line_tax_total' => '2.00', 'line_total_incl_tax' => '12.00',
            ]],
            'payment_terms_code' => 'days_15', 'payment_terms' => 'Sous 15 jours', 'payment_method' => 'Virement', 'public_note' => 'Snapshot note',
            'created_at' => '2026-11-23T09:00:00Z', 'issued_at' => '2026-11-23T10:00:00Z', 'cancelled_at' => null,
            'origin' => ['kind' => 'api', 'source_app' => 'devsys', 'api_update_eligible_for_source' => false],
        ];
        $history = [];
        $client = $this->client($history, new Response(200, ['ETag' => '"5"'], json_encode(['success' => true, 'data' => ['invoice' => $invoice]], JSON_THROW_ON_ERROR)));

        $result = (new DevsysInvoiceReadService(new InvoicesApi($client)))->view(84);

        self::assertSame('api', $result['source']);
        self::assertFalse($result['pdf_enabled']);
        self::assertSame('2026-0008', $result['invoice']['invoice_number']);
        self::assertSame('DEV-TEST-84', $result['invoice']['business_reference']);
        self::assertSame('Snapshot Client', $result['invoice']['client_name']);
        self::assertSame('Snapshot Issuer', $result['invoice']['issuer_legal_name']);
        self::assertSame('Snapshot footer', $result['invoice']['issuer_invoice_footer']);
        self::assertSame('issued', $result['invoice']['status']);
        self::assertSame('Service', $result['lines'][0]['label']);
        self::assertSame('12.00', $result['invoice']['total_incl_tax']);
    }

    public function testUnknownApiProvenanceDoesNotFallBackToLocalInvoiceData(): void
    {
        $invoice = [
            'id' => 85, 'invoice_number' => '2026-0009', 'status' => 'issued', 'revision' => 1,
            'business_reference' => null, 'issue_date' => '2026-11-23', 'due_date' => null, 'currency' => 'EUR',
            'client' => ['name' => 'Snapshot Client'], 'issuer' => ['legal_name' => 'Issuer'],
            'amounts' => ['subtotal_excl_tax' => '1.00', 'discount_total_excl_tax' => '0.00', 'tax_total' => '0.00', 'total_incl_tax' => '1.00'],
            'lines' => [], 'payment_terms_code' => null, 'payment_terms' => null, 'payment_method' => null, 'public_note' => null,
            'created_at' => null, 'issued_at' => null, 'cancelled_at' => null,
        ];
        $history = [];
        $client = $this->client($history, new Response(200, ['ETag' => '"1"'], json_encode(['success' => true, 'data' => ['invoice' => $invoice]], JSON_THROW_ON_ERROR)));
        try {
            (new DevsysInvoiceReadService(new InvoicesApi($client)))->view(85);
            self::fail('Unknown invoice provenance must fail closed.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('provenance', $exception->getMessage());
        }
    }

    private function client(array &$history, Response $response): DevsysApiClient
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($history));

        return new DevsysApiClient(
            new DevsysApiConfig('https://api.example.test/api/v1'),
            new Client(['handler' => $stack, 'http_errors' => false]),
        );
    }

    private function payload(array $items, int $totalPages): array
    {
        return [
            'success' => true,
            'data' => ['items' => $items],
            'meta' => ['pagination' => [
                'page' => 1,
                'per_page' => 25,
                'total' => count($items),
                'total_pages' => $totalPages,
                'has_previous' => false,
                'has_next' => false,
            ]],
        ];
    }

    private function item(int $id, string $status, ?string $number, string $client, string $subtotal, string $tax, string $total): array
    {
        return [
            'id' => $id,
            'invoice_number' => $number,
            'status' => $status,
            'issue_date' => $status === 'draft' ? null : '2026-03-01',
            'due_date' => $status === 'draft' ? null : '2026-03-31',
            'currency' => 'EUR',
            'created_at' => '2026-03-01T00:00:00Z',
            'issued_at' => null,
            'cancelled_at' => null,
            'client' => ['name' => $client],
            'amounts' => [
                'subtotal_excl_tax' => $subtotal,
                'discount_total_excl_tax' => '0.00',
                'tax_total' => $tax,
                'total_incl_tax' => $total,
            ],
        ];
    }
}
