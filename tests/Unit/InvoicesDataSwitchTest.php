<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Invoices;
use App\Repositories\InvoiceRepository;
use App\Services\DevsysClientService;
use App\Services\DevsysInvoiceReadService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\InvoiceTemplateResolver;
use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Configuration\DevsysApiConfig;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;
use Devsys\Shared\Api\Devsys\Invoices\InvoicesApi;
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

    protected function setUp(): void
    {
        $this->hadFlag = array_key_exists('OFFICE_INVOICE_READ_API_ENABLED', $_ENV);
        $this->previousFlag = $_ENV['OFFICE_INVOICE_READ_API_ENABLED'] ?? null;
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

    private function controller(InvoiceService $sql, HttpResponse|\Throwable $apiResponse): Invoices
    {
        $client = new DevsysApiClient(
            new DevsysApiConfig('https://api.example.test/api/v1'),
            new Client(['handler' => HandlerStack::create(new MockHandler([$apiResponse])), 'http_errors' => false]),
        );
        $clients = new DevsysClientService(new ClientsApi($client));
        $templateResolver = (new \ReflectionClass(InvoiceTemplateResolver::class))->newInstanceWithoutConstructor();

        return new Invoices(
            $sql,
            $this->createMock(InvoiceRepository::class),
            $clients,
            new InvoicePdfService($templateResolver),
            new DevsysInvoiceReadService(new InvoicesApi($client)),
        );
    }

    private function errorMessage(string $json): string
    {
        return (string) (json_decode($json, true)['error']['message'] ?? '');
    }
}
