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
