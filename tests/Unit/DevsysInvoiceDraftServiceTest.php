<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DevsysInvoiceDraftService;
use App\Services\InvoiceDraftWriteContextStore;
use App\Services\InvoiceDraftApiInterface;
use App\Services\InvoiceService;
use App\Services\Oidc\OidcSessionService;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiNetworkException;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiResponseException;
use Devsys\Shared\Api\Devsys\Http\DevsysApiResponse;
use Devsys\Shared\Api\Devsys\Invoices\Dto\{InvoiceCommandResult, InvoiceDetails, InvoiceDraftUpdateResult};
use PHPUnit\Framework\TestCase;

final class DevsysInvoiceDraftServiceTest extends TestCase
{
    private mixed $oldEnv;
    private bool $hadEnv;
    private mixed $oldSession;
    private string $oldSessionId;

    protected function setUp(): void
    {
        $this->hadEnv = array_key_exists('OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED', $_ENV);
        $this->oldEnv = $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] ?? null;
        $this->oldSession = $_SESSION ?? null;
        $this->oldSessionId = session_id();
        session_id('invoice-test-' . bin2hex(random_bytes(6)));
        $_SESSION = ['office_identity' => [
            'tenant_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'user_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ]];
    }

    protected function tearDown(): void
    {
        if ($this->hadEnv) $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = $this->oldEnv;
        else unset($_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED']);
        if ($this->oldSession === null) unset($_SESSION);
        else $_SESSION = $this->oldSession;
        if (session_status() !== PHP_SESSION_ACTIVE) session_id($this->oldSessionId);
    }

    public function testHistoricalDraftMutationUsesSqlOnlyAfterApiProvenanceCheck(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $api->expects(self::once())->method('get')->with(17)->willReturn($this->details(17, 'historical'));
        $legacy = $this->legacy();
        $legacy->expects(self::once())->method('saveDraft')->with(['id' => 17], 17)->willReturn(17);
        $service = $this->service($api, $legacy);

        self::assertSame(['id' => 17, 'api_mode' => false], $service->saveDraft(['id' => 17], 17));
    }

    public function testFlagOffKeepsHistoricalCreationAndEditOnSql(): void
    {
        unset($_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED']);
        $api = $this->api();
        $api->expects(self::once())->method('get')->with(19)->willReturn($this->details(19, 'historical'));
        $legacy = $this->legacy();
        $legacy->expects(self::exactly(2))->method('saveDraft')->willReturnOnConsecutiveCalls(60, 19);
        $service = $this->service($api, $legacy);

        self::assertSame(['id' => 60, 'api_mode' => false], $service->saveDraft(['client_uuid' => 'historical'], null));
        self::assertSame(['id' => 19, 'api_mode' => false], $service->saveDraft(['id' => 19], 19));
    }

    public function testApiDraftIsBlockedWhenFlagIsOffWithoutSqlFallback(): void
    {
        unset($_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED']);
        $api = $this->api();
        $api->expects(self::once())->method('get')->with(18)->willReturn($this->details(18, 'api', 'devsys'));
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);

        try {
            $service->saveDraft(['id' => 18], 18);
            self::fail('Expected API-owned update to be rejected.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('désactivée', $exception->getMessage());
        }
    }

    public function testFlagRollbackDoesNotTurnAnApiCreationFormIntoSqlCreation(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $legacy = $this->legacy();
        $legacy->method('form')->willReturn(['invoice' => null, 'lines' => [], 'defaults' => []]);
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $form = $service->form(null);
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'false';
        try {
            $service->saveDraft(['api_creation_context' => $form['api_creation_context']], null);
            self::fail('Expected API-intended creation to fail closed after rollback.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('Aucune création SQL', $exception->getMessage());
        }
    }

    public function testApiCreateUsesStableServerIdentityAndMapsNullableFields(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $legacy = $this->legacy();
        $legacy->expects(self::once())->method('form')->with()->willReturn(['invoice' => null, 'lines' => [], 'defaults' => ['tax_rate' => '20.00']]);
        $legacy->expects(self::never())->method('saveDraft');
        $form = $this->service($api, $legacy)->form(null);
        $creationToken = $form['api_creation_context'];
        $capturedKey = null;
        $capturedEventId = null;
        $api->expects(self::once())->method('createDraft')->willReturnCallback(function ($request, $key) use (&$capturedKey, &$capturedEventId) {
            $payload = $request->toPayload();
            self::assertArrayNotHasKey('due_date', $payload);
            self::assertArrayNotHasKey('payment_method', $payload);
            self::assertArrayNotHasKey('public_note', $payload);
            self::assertSame('devsys', $payload['source_app']);
            self::assertSame('DEV-42', $payload['business_reference']);
            self::assertArrayNotHasKey('total_incl_tax', $payload);
            self::assertSame('2.500', $payload['lines'][0]['quantity']);
            self::assertArrayNotHasKey('discount_type', $payload['lines'][0]);
            self::assertArrayNotHasKey('discount_value', $payload['lines'][0]);
            self::assertArrayNotHasKey('discount_note', $payload['lines'][0]);
            self::assertMatchesRegularExpression('/^office-create-[a-f0-9]{64}$/', $key);
            $capturedKey = $key;
            $capturedEventId = $request->sourceEventId;
            return new InvoiceCommandResult(['id' => 22, 'status' => 'draft'], false, 'hash', 'devsys', $request->sourceEventId, false);
        });
        $api->expects(self::exactly(2))->method('get')->with(22)->willReturn($this->details(22, 'api', 'devsys'));
        $input = [
            'api_creation_context' => $creationToken,
            'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'issue_date' => '2026-11-21', 'due_date' => '', 'payment_terms_code' => '', 'payment_terms' => '',
            'payment_method' => '', 'public_note' => '', 'internal_note' => '', 'business_reference' => 'DEV-42',
            'lines' => [[
                'label' => 'Consulting', 'description' => '', 'quantity' => '2.500', 'unit' => '',
                'unit_price_excl_tax' => '12.50', 'tax_rate' => '20.00', 'discount_type' => '',
                'discount_value' => '0', 'discount_note' => '',
            ]],
        ];
        $result = $this->service($api, $legacy)->saveDraft($input, null);
        $duplicate = $this->service($api, $legacy)->saveDraft($input, null);

        self::assertSame([
            'id' => 22, 'api_mode' => true, 'reload_required' => true,
            'api_totals' => ['subtotal_excl_tax' => '10.00', 'discount_total_excl_tax' => '0.00', 'tax_total' => '2.00', 'total_incl_tax' => '12.00'],
        ], $result);
        self::assertSame(['id' => 22, 'api_mode' => true, 'reload_required' => true, 'api_totals' => $result['api_totals']], $duplicate);
        self::assertSame(22, $duplicate['id']);
        self::assertNotEmpty($capturedKey);
        self::assertNotEmpty($capturedEventId);
        self::assertNotSame($creationToken, $capturedEventId);
    }

    public function testUncertainCreateRetryReusesExactKeyEventAndPayload(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $legacy = $this->legacy();
        $legacy->method('form')->willReturn(['invoice' => null, 'lines' => [], 'defaults' => ['tax_rate' => '20.00']]);
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $form = $service->form(null);
        $keys = [];
        $events = [];
        $payloads = [];
        $calls = 0;
        $api->expects(self::exactly(2))->method('createDraft')->willReturnCallback(function ($request, $key) use (&$keys, &$events, &$payloads, &$calls) {
            $calls++;
            $keys[] = $key;
            $events[] = $request->sourceEventId;
            $payloads[] = $request->toPayload();
            self::assertArrayNotHasKey('business_reference', $request->toPayload());
            if ($calls === 1) throw new DevsysApiNetworkException('timeout');
            return new InvoiceCommandResult(['id' => 23, 'status' => 'draft'], true, 'hash', 'devsys', $request->sourceEventId, true);
        });
        $api->expects(self::once())->method('get')->with(23)->willReturn($this->details(23, 'api', 'devsys'));
        $input = [
            'api_creation_context' => $form['api_creation_context'], 'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'issue_date' => '2026-11-21', 'lines' => [['label' => 'A', 'quantity' => '1.000', 'unit_price_excl_tax' => '5.00', 'tax_rate' => '20.00']],
            'internal_note' => '',
        ];
        try {
            $service->saveDraft($input, null);
            self::fail('Expected the first API request to time out.');
        } catch (DevsysApiNetworkException) {
        }
        $changed = $input;
        $changed['issue_date'] = '2026-11-22';
        try {
            $service->saveDraft($changed, null);
            self::fail('An uncertain create cannot be reused with another payload.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('déjà liée à d’autres valeurs', $exception->getMessage());
        }
        $result = $service->saveDraft($input, null);
        self::assertSame($keys[0], $keys[1]);
        self::assertSame($events[0], $events[1]);
        self::assertSame($payloads[0], $payloads[1]);
        self::assertSame(23, $result['id']);
    }

    public function testUpdateRetriesWithOpeningEtagAndSameKeyAndPayload(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $initial = $this->details(25, 'api', 'devsys', 3, 'DEV-OLD');
        $newer = $this->details(25, 'api', 'devsys', 4, 'DEV-OLD');
        $api->expects(self::exactly(5))->method('get')->with(25)->willReturnOnConsecutiveCalls($initial, $newer, $newer, $newer, $newer);
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $form = $service->form(25);
        self::assertSame('DEV-OLD', $form['invoice']['business_reference']);
        $keys = [];
        $etags = [];
        $calls = 0;
        $api->expects(self::exactly(2))->method('updateDraft')->willReturnCallback(function ($id, $request, $key, $etag) use (&$keys, &$etags, &$calls) {
            $calls++;
            $keys[] = $key;
            $etags[] = $etag;
            self::assertSame(25, $id);
            self::assertSame('devsys', $request->sourceApp);
            self::assertSame('cccccccc-cccc-4ccc-8ccc-cccccccccccc', $request->clientUuid);
            self::assertArrayNotHasKey('business_reference', $request->toPayload());
            if ($calls === 1) throw new DevsysApiNetworkException('offline');
            return new InvoiceDraftUpdateResult(['id' => 25, 'status' => 'draft', 'revision' => 4], 4, '"4"', false, 'hash', 'devsys');
        });
        $input = [
            'id' => 25, 'api_editor_context' => $form['api_editor_context'], 'internal_note' => '',
            'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'issue_date' => '2026-11-21', 'due_date' => '',
            'payment_terms_code' => 'days_15', 'payment_terms' => 'Sous 15 jours', 'payment_method' => 'CB', 'public_note' => '',
            'business_reference' => 'DEV-OLD', 'business_reference_intent' => 'preserve',
            'lines' => [[
                'label' => 'Consulting', 'description' => '', 'quantity' => '1', 'unit' => '',
                'unit_price_excl_tax' => '10.00', 'tax_rate' => '20.00', 'discount_type' => '',
                'discount_value' => '0', 'discount_note' => '',
            ]],
        ];
        try {
            $service->saveDraft($input, 25);
            self::fail('Expected a simulated network failure.');
        } catch (DevsysApiNetworkException) {
        }
        $changed = $input;
        $changed['public_note'] = 'changed during uncertain request';
        try {
            $service->saveDraft($changed, 25);
            self::fail('An uncertain update cannot be reused with another payload.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('sauvegarde API précédente est incertaine', $exception->getMessage());
        }
        self::assertSame(['id' => 25, 'api_mode' => true, 'reload_required' => true], $service->saveDraft($input, 25));
        try {
            $service->saveDraft($input, 25);
            self::fail('The old editor context must require reload after a confirmed update.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('a changé depuis son ouverture', $exception->getMessage());
        }
        self::assertSame(['"3"', '"3"'], $etags);
        self::assertSame($keys[0], $keys[1]);
    }

    public function testBusinessReferenceCanBeSetOrExplicitlyClearedInUpdateDto(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $versions = [3, 4, 5];
        $api->expects(self::exactly(6))->method('get')->willReturnCallback(function () use (&$versions) {
            static $index = 0;
            $revision = $versions[intdiv($index++, 2)];
            return $this->details(50, 'api', 'devsys', $revision, 'DEV-OLD');
        });
        $payloads = [];
        $api->expects(self::exactly(3))->method('updateDraft')->willReturnCallback(function ($id, $request, $key, $etag) use (&$payloads) {
            self::assertSame(50, $id);
            self::assertSame('devsys', $request->sourceApp);
            $payloads[] = $request->toPayload();
            $revision = count($payloads) + 3;
            return new InvoiceDraftUpdateResult(['id' => 50, 'status' => 'draft', 'revision' => $revision], $revision, '"'.$revision.'"', false, 'hash', 'devsys');
        });
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $base = [
            'id' => 50, 'internal_note' => '', 'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'issue_date' => '2026-11-21', 'due_date' => '', 'payment_terms_code' => 'days_15',
            'payment_terms' => 'Sous 15 jours', 'payment_method' => 'CB', 'public_note' => '',
            'lines' => [['label' => 'Consulting', 'quantity' => '1', 'unit_price_excl_tax' => '10.00', 'tax_rate' => '20.00']],
        ];
        foreach ([
            ['business_reference' => 'DEV-OLD', 'business_reference_intent' => 'preserve'],
            ['business_reference' => 'dev-new', 'business_reference_intent' => 'set'],
            ['business_reference' => 'DEV-NEW', 'business_reference_intent' => 'clear'],
        ] as $index => $referenceInput) {
            $form = $service->form(50);
            $service->saveDraft([...$base, ...$referenceInput, 'api_editor_context' => $form['api_editor_context']], 50);
        }

        self::assertArrayNotHasKey('business_reference', $payloads[0]);
        self::assertSame('DEV-NEW', $payloads[1]['business_reference']);
        self::assertArrayHasKey('business_reference', $payloads[2]);
        self::assertNull($payloads[2]['business_reference']);
        self::assertSame('Sous 15 jours', $payloads[1]['payment_terms']);
    }

    public function testForeignSourceBusinessReferenceIsRejectedBeforeUpdate(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $api->expects(self::exactly(2))->method('get')->with(51)->willReturn($this->details(51, 'api', 'devsys'));
        $api->expects(self::never())->method('updateDraft');
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $form = $service->form(51);

        try {
            $service->saveDraft([
                'id' => 51, 'api_editor_context' => $form['api_editor_context'], 'internal_note' => '',
                'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'issue_date' => '2026-11-21',
                'lines' => [['label' => 'X', 'quantity' => '1', 'unit_price_excl_tax' => '1', 'tax_rate' => '20']],
                'business_reference' => 'SPC-1', 'business_reference_intent' => 'set',
            ], 51);
            self::fail('A source-specific reference from another application must be refused.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('source d’origine', $exception->getMessage());
        }
    }

    public function testApiUpdateWithInternalNoteIsRejectedBeforeAnyWrite(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $api->expects(self::exactly(2))->method('get')->with(26)->willReturn($this->details(26, 'api', 'devsys'));
        $api->expects(self::never())->method('updateDraft');
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        $form = $this->service($api, $legacy)->form(26);
        $input = ['id' => 26, 'api_editor_context' => $form['api_editor_context'], 'internal_note' => 'À ne pas perdre'];
        try {
            $this->service($api, $legacy)->saveDraft($input, 26);
            self::fail('Expected internal note to be refused.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('note interne', $exception->getMessage());
        }
    }

    public function testStaleEditorStopsAfterPreconditionFailure(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $details = $this->details(27, 'api', 'devsys');
        $api->expects(self::exactly(3))->method('get')->with(27)->willReturn($details);
        $api->expects(self::once())->method('updateDraft')->willThrowException(new DevsysApiResponseException('stale', 412, '/invoices/27'));
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $form = $service->form(27);
        $input = [
            'id' => 27, 'api_editor_context' => $form['api_editor_context'], 'internal_note' => '',
            'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'issue_date' => '2026-11-21',
            'lines' => [['label' => 'X', 'quantity' => '1', 'unit_price_excl_tax' => '2', 'tax_rate' => '20']],
        ];
        try {
            $service->saveDraft($input, 27);
            self::fail('Expected precondition failure.');
        } catch (DevsysApiResponseException) {
        }
        try {
            $service->saveDraft($input, 27);
            self::fail('Expected stale editor context to be blocked.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('a changé depuis son ouverture', $exception->getMessage());
        }
    }

    public function testOtherApiSourceCannotBeIssuedOrDeletedThroughSql(): void
    {
        $api = $this->api();
        $api->expects(self::exactly(2))->method('get')->with(31)->willReturn($this->details(31, 'api', 'spotcard'));
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('issueDraft');
        $legacy->expects(self::never())->method('deleteDraft');
        $service = $this->service($api, $legacy);
        foreach (['issue', 'delete'] as $action) {
            try {
                $action === 'issue' ? $service->issueDraft(['id' => 31], 31) : $service->deleteDraft(31);
                self::fail('Expected API-origin action to be blocked.');
            } catch (\LogicException) {
                self::assertTrue(true);
            }
        }
    }

    public function testForeignSourceApiDraftIsReadOnlyWithoutServerCapability(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $api->expects(self::once())->method('get')->with(44)->willReturn($this->details(44, 'api', 'spotcard', 3, 'SPC-REC-44'));
        $api->expects(self::never())->method('updateDraft');
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('form');
        $legacy->expects(self::never())->method('saveDraft');

        try {
            $this->service($api, $legacy)->form(44);
            self::fail('A same-tenant read without the API capability must not grant edit access.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('ne peut pas être modifiée', $exception->getMessage());
        }
    }

    public function testAuthorizedOfficeTenantAdminUpdatesForeignDraftThroughApiAndPreservesOriginReference(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $details = $this->details(45, 'api', 'spotcard', 3, 'SPC-REC-45', true);
        $api = $this->api();
        $api->expects(self::exactly(2))->method('get')->with(45)->willReturn($details);
        $api->expects(self::once())->method('updateDraft')->willReturnCallback(function ($id, $request, $key, $etag) {
            self::assertSame(45, $id);
            self::assertSame('devsys', $request->sourceApp, 'Office authenticates as the editor; it does not impersonate the origin.');
            self::assertSame('"3"', $etag);
            self::assertArrayNotHasKey('business_reference', $request->toPayload(), 'The SPC reference is preserved by omission.');
            self::assertMatchesRegularExpression('/^office-update-[a-f0-9]{64}$/', $key);
            return new InvoiceDraftUpdateResult(['id' => 45, 'status' => 'draft', 'revision' => 4], 4, '"4"', false, 'hash', 'devsys');
        });
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('form');
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $form = $service->form(45);
        self::assertTrue($form['api_mode']);
        self::assertTrue($form['api_writable']);
        self::assertSame('SPC-REC-45', $form['invoice']['business_reference']);
        self::assertSame('SPC-[A-Za-z0-9][A-Za-z0-9._/-]{0,95}', $form['business_reference_pattern']);

        $result = $service->saveDraft([
            'id' => 45, 'api_editor_context' => $form['api_editor_context'], 'internal_note' => '',
            'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'issue_date' => '2026-11-21',
            'business_reference' => 'SPC-REC-45', 'business_reference_intent' => 'preserve',
            'lines' => [['label' => 'Consulting', 'quantity' => '1', 'unit_price_excl_tax' => '10', 'tax_rate' => '20']],
        ], 45);

        self::assertSame(['id' => 45, 'api_mode' => true, 'reload_required' => true], $result);
    }

    public function testAuthorizedOfficeAdminCanReplaceForeignReferenceUsingItsOriginPrefix(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'true';
        $api = $this->api();
        $details = $this->details(46, 'api', 'spotcard', 3, 'SPC-OLD', true);
        $api->expects(self::exactly(2))->method('get')->with(46)->willReturn($details);
        $api->expects(self::once())->method('updateDraft')->willReturnCallback(function ($id, $request) {
            self::assertSame(46, $id);
            self::assertSame('devsys', $request->sourceApp);
            self::assertSame('SPC-NEW', $request->toPayload()['business_reference']);

            return new InvoiceDraftUpdateResult(['id' => 46, 'status' => 'draft', 'revision' => 4], 4, '"4"', false, 'hash', 'devsys');
        });
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        $service = $this->service($api, $legacy);
        $form = $service->form(46);

        $service->saveDraft([
            'id' => 46, 'api_editor_context' => $form['api_editor_context'], 'internal_note' => '',
            'client_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'issue_date' => '2026-11-21',
            'business_reference' => 'SPC-NEW', 'business_reference_intent' => 'set',
            'lines' => [['label' => 'Consulting', 'quantity' => '1', 'unit_price_excl_tax' => '10', 'tax_rate' => '20']],
        ], 46);
    }

    public function testActionAvailabilityMarksApiActionsUnavailableAndHistoricalActionsAvailable(): void
    {
        $_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] = 'false';
        $api = $this->api();
        $api->expects(self::exactly(3))->method('get')->willReturnOnConsecutiveCalls(
            $this->details(41, 'historical'),
            $this->details(42, 'api', 'devsys'),
            $this->details(43, 'api', 'spotcard'),
        );
        $service = $this->service($api, $this->legacy());
        self::assertSame(['api_mode' => false, 'can_edit' => true, 'can_issue' => true, 'can_delete' => true], $service->actionAvailability(41));
        self::assertSame(['api_mode' => true, 'can_edit' => false, 'can_issue' => false, 'can_delete' => false], $service->actionAvailability(42));
        self::assertSame(['api_mode' => true, 'can_edit' => false, 'can_issue' => false, 'can_delete' => false], $service->actionAvailability(43));
    }

    public function testMissingProvenanceFailsClosed(): void
    {
        $api = $this->api();
        $api->expects(self::once())->method('get')->with(32)->willReturn($this->details(32, null));
        $legacy = $this->legacy();
        $legacy->expects(self::never())->method('saveDraft');
        try {
            $this->service($api, $legacy)->saveDraft(['id' => 32], 32);
            self::fail('Expected unknown provenance to fail closed.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('provenance', $exception->getMessage());
        }
    }

    public function testBrowserCannotSelectTenantIssuerLegalNumberSourceOrRetryKeys(): void
    {
        $service = $this->service($this->api(), $this->legacy());
        foreach (['tenant_id', 'tenant_uuid', 'issuer_id', 'invoice_number', 'source_app', 'source_event_id', 'idempotency_key'] as $selector) {
            try {
                $service->assertNoTenantSelectors([$selector => 'forged']);
                self::fail('Expected the browser selector to be rejected: '.$selector);
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    private function service(InvoiceDraftApiInterface $api, InvoiceService $legacy): DevsysInvoiceDraftService
    {
        return new DevsysInvoiceDraftService($api, $legacy, new InvoiceDraftWriteContextStore(new OidcSessionService()));
    }

    private function api(): InvoiceDraftApiInterface
    {
        return $this->createMock(InvoiceDraftApiInterface::class);
    }

    private function legacy(): InvoiceService
    {
        return $this->getMockBuilder(InvoiceService::class)->disableOriginalConstructor()->onlyMethods(['form', 'saveDraft', 'issueDraft', 'deleteDraft'])->getMock();
    }

    private function details(int $id, ?string $kind, ?string $sourceApp = null, int $revision = 3, ?string $businessReference = null, ?bool $updateEligible = null): InvoiceDetails
    {
        $invoice = [
            'id' => $id, 'invoice_number' => null, 'status' => 'draft', 'revision' => $revision,
            'business_reference' => $businessReference,
            'issue_date' => '2026-11-21', 'due_date' => null, 'currency' => 'EUR',
            'client' => ['name' => 'Snapshot Client', 'uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'],
            'issuer' => null,
            'amounts' => ['subtotal_excl_tax' => '10.00', 'discount_total_excl_tax' => '0.00', 'tax_total' => '2.00', 'total_incl_tax' => '12.00'],
            'lines' => [[
                'id' => 1, 'position' => 1, 'label' => 'Consulting', 'description' => null,
                'quantity' => '1.000', 'unit' => null, 'unit_price_excl_tax' => '10.00', 'discount_type' => null,
                'discount_value' => null, 'discount_note' => null, 'tax_rate' => '20.00',
                'line_subtotal_excl_tax' => '10.00', 'line_discount_excl_tax' => '0.00', 'line_tax_total' => '2.00', 'line_total_incl_tax' => '12.00',
            ]],
            'payment_terms_code' => 'days_15', 'payment_terms' => 'Sous 15 jours', 'payment_method' => 'CB', 'public_note' => null,
            'created_at' => '2026-11-21T00:00:00Z', 'issued_at' => null, 'cancelled_at' => null,
            'origin' => $kind === null ? null : ['kind' => $kind, 'source_app' => $sourceApp, 'api_update_eligible_for_source' => $updateEligible ?? ($kind === 'api' && $sourceApp === 'devsys')],
        ];
        if ($kind === null) unset($invoice['origin']);
        return InvoiceDetails::fromApiResponse(new DevsysApiResponse(200, ['ETag' => ['"' . $revision . '"']], ['success' => true, 'data' => ['invoice' => $invoice]], 'request-safe'));
    }
}
