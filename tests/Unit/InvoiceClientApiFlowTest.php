<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CompanySettingsRepository;
use App\Repositories\InvoiceRepository;
use App\Services\CompanySettingsService;
use App\Services\DevsysClientService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceService;
use App\Services\InvoiceTemplateResolver;
use App\Services\TenantContext;
use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Configuration\DevsysApiConfig;
use Devsys\Shared\Api\Devsys\Http\DevsysApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class InvoiceClientApiFlowTest extends TestCase
{
    public const UUID = '6b4b8f7a-987d-4ee2-9d27-c420a6d7b6f9';

    public function testInvoiceSearchUsesActiveApiOptionsAndPublicUuid(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode([
            'success' => true,
            'data' => ['items' => [$this->listItem()]],
            'meta' => ['pagination' => ['page' => 1, 'per_page' => 25, 'total' => 1, 'total_pages' => 1]],
        ], JSON_THROW_ON_ERROR))]));
        $stack->push(\GuzzleHttp\Middleware::history($history));

        $options = $this->service($stack)->searchInvoiceClientOptions('dev');

        self::assertSame([['id' => self::UUID, 'label' => 'DevSys']], $options);
        $query = $history[0]['request']->getUri()->getQuery();
        self::assertStringContainsString('search=dev', $query);
        self::assertStringContainsString('status=active', $query);
        self::assertStringContainsString('sort=display_name', $query);
        self::assertStringContainsString('direction=asc', $query);
    }

    public function testInvoiceClientMapsCompanyAndPersonDataFromApi(): void
    {
        $service = $this->service(HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => ['client' => $this->clientData()]], JSON_THROW_ON_ERROR)),
        ])));

        self::assertSame([
            'uuid' => self::UUID, 'display_name' => 'DevSys', 'company_name' => 'DevSys', 'legal_name' => 'DevSys SAS',
            'contact_first_name' => 'Jean', 'contact_last_name' => 'Dupont', 'email' => 'jean@example.test',
            'phone' => '+33 1', 'address_line1' => '1 rue de Paris', 'address_line2' => 'Bâtiment A',
            'postal_code' => '75001', 'city' => 'Paris', 'country' => 'FR', 'siret' => '12345678901234',
            'siren' => '123456789', 'vat_number' => 'FR123456789', 'status' => 'active',
        ], $service->invoiceClient(self::UUID));
    }

    public function testInvoiceClientUsesContactNameWhenCompanyNameIsMissing(): void
    {
        $data = $this->clientData();
        $data['company_name'] = null;
        $service = $this->service(HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'data' => ['client' => $data]], JSON_THROW_ON_ERROR)),
        ])));

        self::assertSame('Jean Dupont', $service->invoiceClient(self::UUID)['display_name']);
    }

    public function testSaveDraftUsesApiSnapshotAndInternalId(): void
    {
        $repository = new CapturingInvoiceRepository();
        $service = new InvoiceService(
            $repository,
            new InvoiceCalculationService(),
            $this->createMock(CompanySettingsRepository::class),
            $this->createMock(CompanySettingsService::class),
            $this->service(HandlerStack::create(new MockHandler([
                new Response(200, [], json_encode(['success' => true, 'data' => ['client' => $this->clientData()]], JSON_THROW_ON_ERROR)),
            ]))),
        );

        $service->saveDraft($this->invoiceInput());

        self::assertSame(42, $repository->invoice[0]);
        self::assertSame('DevSys', $repository->invoice[4]);
        self::assertSame('Jean Dupont', $repository->invoice[5]);
        self::assertSame('1 rue de Paris', $repository->invoice[8]);
        self::assertSame('jean@example.test', $repository->invoice[6]);
        self::assertSame('FR123456789', $repository->invoice[14]);
    }

    public function testInvalidUuidIsRejectedBeforeCallingClientService(): void
    {
        $service = new InvoiceService(
            new CapturingInvoiceRepository(), new InvoiceCalculationService(),
            $this->createMock(CompanySettingsRepository::class), $this->createMock(CompanySettingsService::class),
            $this->service(HandlerStack::create(new MockHandler())),
        );

        $this->expectExceptionMessage('Le client sélectionné est invalide.');
        $service->saveDraft($this->invoiceInput(['client_uuid' => 'not-a-uuid']));
    }

    public function testInactiveClientIsRejected(): void
    {
        $data = $this->clientData();
        $data['status'] = 'inactive';
        $service = new InvoiceService(
            new CapturingInvoiceRepository(), new InvoiceCalculationService(),
            $this->createMock(CompanySettingsRepository::class), $this->createMock(CompanySettingsService::class),
            $this->service(HandlerStack::create(new MockHandler([
                new Response(200, [], json_encode(['success' => true, 'data' => ['client' => $data]], JSON_THROW_ON_ERROR)),
            ]))),
        );

        $this->expectExceptionMessage('Seuls les clients actifs');
        $service->saveDraft($this->invoiceInput());
    }

    public function testExistingDraftRestoresUuidWithoutCallingTheApi(): void
    {
        $repository = new CapturingInvoiceRepository();
        $repository->existing = ['id' => 10, 'status' => 'draft', 'client_id' => 42, 'client_name' => 'Snapshot DevSys'];
        $repository->existingLines = [['label' => 'Développement']];
        $service = new InvoiceService(
            $repository, new InvoiceCalculationService(), $this->createMock(CompanySettingsRepository::class),
            $this->createMock(CompanySettingsService::class), $this->service(HandlerStack::create(new MockHandler())),
        );

        $form = $service->form(10);

        self::assertSame(self::UUID, $form['invoice']['client_uuid']);
        self::assertSame($repository->existingLines, $form['lines']);
        self::assertSame('Snapshot DevSys', $form['invoice']['client_name']);
    }

    public function testIssueDraftUsesCurrentApiSnapshotAndNeverIssuesInactiveClient(): void
    {
        $repository = new CapturingInvoiceRepository();
        $repository->existing = ['id' => 10, 'status' => 'draft', 'currency' => 'EUR'];
        $company = $this->createMock(CompanySettingsRepository::class);
        $company->method('find')->willReturn(['legal_name' => 'Issuer']);
        $companyService = $this->createMock(CompanySettingsService::class);
        $companyService->method('invoiceIssuerSnapshot')->willReturn(['legal_name' => 'Issuer']);
        $systemRoot = sys_get_temp_dir() . '/invoice-template-' . uniqid('', true);
        mkdir($systemRoot . '/v1', 0777, true);
        file_put_contents($systemRoot . '/v1/first-page.pdf', '%PDF-test');
        $service = new InvoiceService(
            $repository, new InvoiceCalculationService(), $company,
            $companyService, $this->service(HandlerStack::create(new MockHandler([
                new Response(200, [], json_encode(['success' => true, 'data' => ['client' => $this->clientData()]], JSON_THROW_ON_ERROR)),
            ]))),
            new InvoiceTemplateResolver((new \ReflectionClass(TenantContext::class))->newInstanceWithoutConstructor(), $company, null, $systemRoot),
        );

        $service->issueDraft($this->invoiceInput(), 10);

        self::assertSame(42, $repository->issuedInvoice[0]);
        self::assertSame('DevSys', $repository->issuedInvoice[4]);
        self::assertSame('jean@example.test', $repository->issuedInvoice[6]);
        self::assertSame('1 rue de Paris', $repository->issuedInvoice[8]);
        self::assertSame('Paris', $repository->issuedInvoice[11]);
        self::assertSame('12345678901234', $repository->issuedInvoice[13]);
        self::assertSame('FR123456789', $repository->issuedInvoice[14]);
        self::assertSame('system', $repository->templateSource);
        self::assertSame('v1', $repository->templateVersion);
    }

    public function testClientResolutionIsScopedToTenantInBothDirections(): void
    {
        $tenant = (new \ReflectionClass(TenantContext::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(TenantContext::class, 'resolved'))->setValue($tenant, true);
        (new \ReflectionProperty(TenantContext::class, 'tenant'))->setValue($tenant, ['id' => 7]);
        $repository = (new \ReflectionClass(InvoiceRepository::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(InvoiceRepository::class, 'tenant'))->setValue($repository, $tenant);
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE clients (id INTEGER, uuid TEXT, tenant_id INTEGER)');
        $pdo->exec("INSERT INTO clients VALUES (42, '" . self::UUID . "', 7), (84, 'other-tenant', 8)");
        (new \ReflectionProperty(\App\Core\BaseRepository::class, 'pdo'))->setValue($repository, $pdo);

        self::assertSame(42, $repository->clientInternalIdByUuid(self::UUID));
        self::assertSame(self::UUID, $repository->clientUuidForInternalId(42));
        self::assertNull($repository->clientInternalIdByUuid('other-tenant'));
        self::assertNull($repository->clientUuidForInternalId(84));
    }

    private function service(HandlerStack $stack): DevsysClientService
    {
        return new DevsysClientService(new ClientsApi(new DevsysApiClient(
            new DevsysApiConfig('https://api.example.test/api/v1'),
            new Client(['handler' => $stack, 'base_uri' => 'https://api.example.test/api/v1/']),
        )));
    }

    private function invoiceInput(array $override = []): array
    {
        return array_replace([
            'client_uuid' => self::UUID, 'issue_date' => '2026-08-27', 'due_date' => '',
            'payment_terms' => '', 'payment_method' => '', 'public_note' => '', 'internal_note' => '',
            'lines' => [['label' => 'Conseil', 'description' => '', 'quantity' => '1', 'unit' => 'heure', 'unit_price_excl_tax' => '100', 'discount_type' => '', 'discount_value' => '0', 'discount_note' => '', 'tax_rate' => '20']],
        ], $override);
    }

    private function listItem(): array
    {
        return ['id' => self::UUID, 'display_name' => 'DevSys', 'legal_type' => ['slug' => 'company', 'name' => 'Société'], 'client_category' => null, 'email' => 'jean@example.test', 'phone' => '+33 1', 'city' => 'Paris', 'postal_code' => '75001', 'status' => 'active', 'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-02T00:00:00Z'];
    }

    private function clientData(): array
    {
        return ['id' => self::UUID, 'legal_type' => ['slug' => 'company', 'name' => 'Société'], 'client_category' => null, 'company_name' => 'DevSys', 'legal_name' => 'DevSys SAS', 'contact' => ['first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'jean@example.test', 'phone' => '+33 1'], 'address' => ['line1' => '1 rue de Paris', 'line2' => 'Bâtiment A', 'postal_code' => '75001', 'city' => 'Paris', 'country_code' => 'FR'], 'business' => ['siret' => '12345678901234', 'siren' => '123456789', 'vat_number' => 'FR123456789'], 'website' => null, 'notes' => null, 'status' => 'active', 'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-02T00:00:00Z'];
    }
}

final class CapturingInvoiceRepository extends InvoiceRepository
{
    public array $invoice = [];
    public array $issuedInvoice = [];
    public array $issuer = [];
    public ?string $templateSource = null;
    public ?string $templateVersion = null;
    public array $existing = [];
    public array $existingLines = [];

    public function __construct()
    {
    }

    public function clientInternalIdByUuid(string $uuid): ?int
    {
        return $uuid === InvoiceClientApiFlowTest::UUID ? 42 : null;
    }

    public function createDraft(array $invoice, array $lines): int
    {
        $this->invoice = $invoice;
        return 99;
    }

    public function find(int $id): ?array
    {
        return $this->existing ?: null;
    }

    public function lines(int $invoiceId): array
    {
        return $this->existingLines;
    }

    public function clientUuidForInternalId(int $clientId): ?string
    {
        return $clientId === 42 ? InvoiceClientApiFlowTest::UUID : null;
    }

    public function issueDraft(int $id, array $invoice, array $lines, array $issuer, string $templateSource, string $templateVersion): string
    {
        $this->issuedInvoice = $invoice;
        $this->issuer = $issuer;
        $this->templateSource = $templateSource;
        $this->templateVersion = $templateVersion;
        return 'F2026-0001';
    }
}
