<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Invoices;
use App\Repositories\InvoiceRepository;
use App\Services\DevsysClientService;
use App\Services\DevsysInvoiceReadService;
use App\Services\DevsysInvoiceDraftService;
use App\Services\InvoiceDraftWriteContextStore;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\InvoiceTemplateResolver;
use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Configuration\DevsysApiConfig;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;
use Devsys\Shared\Api\Devsys\Invoices\InvoicesApi;
use App\Services\Oidc\OidcSessionService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as HttpResponse;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;

final class InvoicesDataSwitchTest extends TestCase
{
    private mixed $previousFlag;
    private bool $hadFlag;
    private mixed $previousGet;
    private mixed $previousDraftWriteFlag;
    private bool $hadDraftWriteFlag;

    protected function setUp(): void
    {
        $this->hadFlag = array_key_exists('OFFICE_INVOICE_READ_API_ENABLED', $_ENV);
        $this->previousFlag = $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] ?? null;
        $this->hadDraftWriteFlag = array_key_exists('OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED', $_ENV);
        $this->previousDraftWriteFlag = $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] ?? null;
        $this->previousGet = $_GET;
        $_GET = ['page' => '1'];
    }

    protected function tearDown(): void
    {
        if ($this->hadFlag) {
            $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] = $this->previousFlag;
        } else {
            unset($_ENV['OFFICE_INVOICE_READ_API_ENABLED']);
        }
        if ($this->hadDraftWriteFlag) {
            $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = $this->previousDraftWriteFlag;
        } else {
            unset($_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED']);
        }
        $_GET = $this->previousGet;
    }

    public function testApiFlagDefaultsToSqlList(): void
    {
        unset($_ENV['OFFICE_INVOICE_READ_API_ENABLED']);
        $sql = $this->createMock(InvoiceService::class);
        $sql->expects(self::once())->method('list')->with($_GET)->willReturn(['rows' => [], 'pages' => 1]);

        $response = $this->controller($sql, new HttpResponse(500, [], '{}'))->data();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['rows' => [], 'pages' => 1], json_decode($response->toJson(), true)['data']);
    }

    public function testApiFlagUsesOnlyApiAndShowsApiFailureWithoutSqlFallback(): void
    {
        $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] = 'true';
        $sql = $this->createMock(InvoiceService::class);
        $sql->expects(self::never())->method('list');

        $response = $this->controller($sql, new HttpResponse(500, [], '{"success":false}'))->data();

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('temporairement indisponible', $this->errorMessage($response->toJson()));
    }

    public function testApiListUsesServerProvenanceToHideForbiddenDraftActions(): void
    {
        $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] = 'true';
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'false';
        $items = [
            $this->listItem(77),
            $this->listItem(78),
        ];
        $list = new HttpResponse(200, [], json_encode([
            'success' => true,
            'data' => ['items' => $items],
            'meta' => ['pagination' => ['page' => 1, 'per_page' => 25, 'total' => 2, 'total_pages' => 1, 'has_previous' => false, 'has_next' => false]],
        ], JSON_THROW_ON_ERROR));
        $sql = $this->createMock(InvoiceService::class);
        $sql->expects(self::never())->method('list');

        $response = $this->controller($sql, [$list, $this->apiDraftResponse(77), $this->apiDraftResponse(78, 'historical', null)])->data();
        $rows = json_decode($response->toJson(), true, 512, JSON_THROW_ON_ERROR)['data']['rows'];

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($rows[0]['api_mode']);
        self::assertFalse($rows[0]['can_edit']);
        self::assertFalse($rows[0]['can_issue']);
        self::assertFalse($rows[0]['can_delete']);
        self::assertFalse($rows[1]['api_mode']);
        self::assertTrue($rows[1]['can_edit']);
        self::assertTrue($rows[1]['can_issue']);
        self::assertTrue($rows[1]['can_delete']);
    }

    public function testApiHttpErrorsRemainVisibleAndUseSafeMessages(): void
    {
        $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] = 'true';
        foreach ([
            [401, 401, 'Reconnectez-vous'],
            [403, 403, 'refusé'],
            [404, 404, 'liste des factures est introuvable'],
            [422, 503, 'temporairement indisponible'],
            [500, 503, 'temporairement indisponible'],
        ] as [$apiStatus, $expectedStatus, $message]) {
            $sql = $this->createMock(InvoiceService::class);
            $sql->expects(self::never())->method('list');
            $response = $this->controller($sql, new HttpResponse($apiStatus, ['X-Request-Id' => 'req-safe'], '{"success":false}'))->data();

            self::assertSame($expectedStatus, $response->getStatusCode());
            self::assertStringContainsString($message, $this->errorMessage($response->toJson()));
            self::assertStringNotContainsString('req-safe', $response->toJson());
        }
    }

    public function testMalformedResponseAndNetworkFailureAreUnavailable(): void
    {
        $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] = 'true';
        $sql = $this->createMock(InvoiceService::class);
        $sql->expects(self::never())->method('list');
        $malformed = $this->controller($sql, new HttpResponse(200, [], '{"success":true,"data":[]}'))->data();
        self::assertSame(503, $malformed->getStatusCode());
        self::assertStringContainsString('réponse invalide', $this->errorMessage($malformed->toJson()));

        $networkFailure = new ConnectException('offline', new Request('GET', 'https://api.example.test/api/v1/invoices'));
        $network = $this->controller($sql, $networkFailure)->data();
        self::assertSame(503, $network->getStatusCode());
        self::assertStringContainsString('temporairement indisponible', $this->errorMessage($network->toJson()));
    }

    public function testDirectSaveIssueAndDeletePostsCannotMutateApiDraft(): void
    {
        unset($_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED']);
        foreach (['save', 'issue', 'delete'] as $action) {
            $sql = $this->createMock(InvoiceService::class);
            $sql->expects(self::never())->method('saveDraft');
            $sql->expects(self::never())->method('issueDraft');
            $sql->expects(self::never())->method('deleteDraft');
            $controller = $this->controller($sql, $this->apiDraftResponse(77));
            $response = match ($action) {
                'save' => $controller->save(['vars' => ['id' => 77, 'internal_note' => '']]),
                'issue' => $controller->issue(['vars' => ['id' => 77]]),
                default => $controller->delete(['vars' => ['id' => 77]]),
            };
            self::assertSame(409, $response->getStatusCode(), $action);
            self::assertStringNotContainsString('internal_note', $response->toJson());
        }
    }

    public function testApiGetFailureDoesNotAuthorizeHistoricalSqlMutation(): void
    {
        unset($_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED']);
        $sql = $this->createMock(InvoiceService::class);
        $sql->expects(self::never())->method('saveDraft');
        $response = $this->controller($sql, new HttpResponse(403, [], '{"success":false,"error":{"code":"FORBIDDEN","message":"denied"}}'))
            ->save(['vars' => ['id' => 78]]);
        self::assertSame(403, $response->getStatusCode());
        self::assertStringNotContainsString('denied', $response->toJson());
    }

    public function testIssuedApiPdfReturnsArchivedBinaryThroughSharedSdk(): void
    {
        $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] = 'true';
        $invoice = [
            'id'=>84,'invoice_number'=>'2026-0084','business_reference'=>'TEST-84','status'=>'issued','revision'=>1,
            'issue_date'=>'2026-11-25','due_date'=>'2026-12-25','currency'=>'EUR',
            'client'=>['name'=>'Fixture Client','contact_name'=>null,'email'=>null,'phone'=>null,'address_line1'=>'1 Test St','address_line2'=>null,'postal_code'=>'75000','city'=>'Paris','country'=>'FR','siret'=>null,'vat_number'=>null,'uuid'=>null],
            'issuer'=>['legal_name'=>'Fixture Issuer','trading_name'=>null,'legal_form'=>null,'share_capital'=>null,'address_line1'=>'2 Test St','address_line2'=>null,'postal_code'=>'75001','city'=>'Paris','country'=>'FR','email'=>null,'phone'=>null,'website'=>null,'siret'=>null,'siren'=>null,'vat_number'=>null,'ape_code'=>null,'rcs_city'=>null,'bank_name'=>null,'iban'=>null,'bic'=>null,'invoice_footer'=>null],
            'amounts'=>['subtotal_excl_tax'=>'10.00','discount_total_excl_tax'=>'0.00','tax_total'=>'2.00','total_incl_tax'=>'12.00'],
            'lines'=>[['id'=>1,'position'=>1,'label'=>'Fixture line','description'=>null,'quantity'=>'1.000','unit'=>'unit','unit_code'=>'C62','unit_price_excl_tax'=>'10.00','discount_type'=>null,'discount_value'=>null,'discount_note'=>null,'tax_rate'=>'20.00','tax_category_code'=>'S','line_subtotal_excl_tax'=>'10.00','line_discount_excl_tax'=>'0.00','line_tax_total'=>'2.00','line_total_incl_tax'=>'12.00']],
            'payment_terms_code'=>null,'payment_terms'=>null,'payment_method'=>null,'public_note'=>null,'created_at'=>'2026-11-25T00:00:00Z','issued_at'=>'2026-11-25T00:00:00Z','cancelled_at'=>null,
            'origin'=>['kind'=>'api','source_app'=>'devsys','api_update_eligible_for_source'=>false],
        ];
        $pdf = "%PDF-1.7\nfixture-pdf";
        $controller = $this->controller($this->createMock(InvoiceService::class), [
            new HttpResponse(200, ['ETag'=>'"1"'], json_encode(['success'=>true,'data'=>['invoice'=>$invoice]], JSON_THROW_ON_ERROR)),
            new HttpResponse(200, [], '{"success":true,"data":{"available":true,"invoice_number":"2026-0084","sha256":"'.hash('sha256',$pdf).'","size":'.strlen($pdf).'}}'),
            new HttpResponse(200, ['Content-Type'=>'application/pdf','Content-Length'=>(string)strlen($pdf)], $pdf),
        ]);
        $response = $controller->pdf(['id'=>84]);
        self::assertSame(200, $response->getStatusCode());
        ob_start(); $response->send(); $body = ob_get_clean();
        self::assertSame($pdf, $body);
    }

    private function controller(InvoiceService $sql, HttpResponse|\Throwable|array $apiResponse): Invoices
    {
        $client = new DevsysApiClient(
            new DevsysApiConfig('https://api.example.test/api/v1'),
            new Client(['handler' => HandlerStack::create(new MockHandler(is_array($apiResponse) ? $apiResponse : [$apiResponse])), 'http_errors' => false]),
        );
        $clients = new DevsysClientService(new ClientsApi($client));
        $templateResolver = (new \ReflectionClass(InvoiceTemplateResolver::class))->newInstanceWithoutConstructor();

        $invoiceApi = new InvoicesApi($client);
        return new Invoices(
            $sql,
            $this->createMock(InvoiceRepository::class),
            $clients,
            new InvoicePdfService($templateResolver),
            new DevsysInvoiceReadService($invoiceApi),
            new DevsysInvoiceDraftService(new \App\Services\SharedInvoiceDraftApi($invoiceApi), $sql, new InvoiceDraftWriteContextStore(new OidcSessionService())),
        );
    }

    private function errorMessage(string $json): string
    {
        return (string) (json_decode($json, true)['error']['message'] ?? '');
    }

    private function apiDraftResponse(int $id, string $originKind = 'api', ?string $sourceApp = 'devsys'): HttpResponse
    {
        $invoice = [
            'id' => $id, 'invoice_number' => null, 'status' => 'draft', 'revision' => 1, 'business_reference' => null,
            'issue_date' => '2026-11-21', 'due_date' => null, 'currency' => 'EUR',
            'client' => ['name' => 'Snapshot client', 'uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'],
            'issuer' => null,
            'amounts' => ['subtotal_excl_tax' => '10.00', 'discount_total_excl_tax' => '0.00', 'tax_total' => '2.00', 'total_incl_tax' => '12.00'],
            'lines' => [], 'payment_terms_code' => null, 'payment_terms' => null, 'payment_method' => null,
            'public_note' => null, 'created_at' => '2026-11-21T00:00:00Z', 'issued_at' => null, 'cancelled_at' => null,
            'origin' => ['kind' => $originKind, 'source_app' => $sourceApp, 'api_update_eligible_for_source' => $originKind === 'api' && $sourceApp === 'devsys'],
        ];
        return new HttpResponse(200, ['ETag' => ['"1"']], json_encode(['success' => true, 'data' => ['invoice' => $invoice]], JSON_THROW_ON_ERROR));
    }

    private function listItem(int $id): array
    {
        return [
            'id' => $id, 'invoice_number' => null, 'status' => 'draft', 'issue_date' => '2026-11-21',
            'due_date' => null, 'currency' => 'EUR', 'created_at' => '2026-11-21T00:00:00Z',
            'issued_at' => null, 'cancelled_at' => null, 'client' => ['name' => 'Snapshot client'],
            'amounts' => ['subtotal_excl_tax' => '10.00', 'discount_total_excl_tax' => '0.00', 'tax_total' => '2.00', 'total_incl_tax' => '12.00'],
        ];
    }
}
