<?php

declare(strict_types=1);

namespace App\Services;

use Devsys\Shared\Api\Devsys\Invoices\Dto\{CreateInvoiceDraftRequest, InvoiceDetails, InvoiceDraftLine, UpdateInvoiceDraftRequest};

/** Owns the API draft path and fails closed whenever provenance is uncertain. */
final class DevsysInvoiceDraftService
{
    public function __construct(
        private readonly InvoiceDraftApiInterface $invoices,
        private readonly InvoiceService $legacy,
        private readonly InvoiceDraftWriteContextStore $contexts,
    ) {
    }

    public function enabled(): bool
    {
        return ($_ENV['OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED'] ?? 'false') === 'true';
    }

    /** Provenance-backed UI capabilities; mutation endpoints still repeat the check. */
    public function actionAvailability(int $id): array
    {
        $this->assertSharedContractReady();
        $details = $this->invoices->get($id);
        $this->assertRequestedId($details, $id);
        if ($details->origin === null) {
            throw new \LogicException('L’API n’a pas fourni la provenance de cette facture.');
        }
        $isDraft = $details->status === 'draft' && $details->invoiceNumber === null;
        if (!$isDraft) {
            return ['api_mode' => $details->origin->kind === 'api', 'can_edit' => false, 'can_issue' => false, 'can_delete' => false];
        }
        if ($details->origin->kind === 'historical') {
            return ['api_mode' => false, 'can_edit' => true, 'can_issue' => true, 'can_delete' => true];
        }
        $apiOwned = $details->origin->kind === 'api';
        $updateContractReady = false;
        if ($apiOwned && $this->enabled()) {
            try {
                $this->assertUpdateContractReady();
                $updateContractReady = true;
            } catch (\LogicException) {
                // An older SDK cannot safely preserve/clear the reference; hide the edit action.
            }
        }
        return [
            'api_mode' => true,
            'can_edit' => $apiOwned && $this->enabled() && $updateContractReady && $this->eligible($details),
            'can_issue' => false,
            'can_delete' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function form(?int $id): array
    {
        if ($id === null) {
            if ($this->enabled()) $this->assertCreateContractReady();
            $form = $this->legacy->form();
            $form['api_mode'] = $this->enabled();
            $form['api_writable'] = $this->enabled();
            $form['business_reference_prefix'] = 'DEV-';
            $form['business_reference_source_label'] = 'DevSys';
            $form['business_reference_pattern'] = 'DEV-[A-Za-z0-9][A-Za-z0-9._/-]{0,95}';
            $form['business_reference_locked'] = false;
            $form['api_editor_context'] = '';
            $form['api_creation_context'] = $this->enabled() ? $this->contexts->newCreateContext() : '';
            return $form;
        }

        $details = $this->invoices->get($id);
        $this->assertRequestedId($details, $id);
        $origin = $this->origin($details);
        if ($origin === 'historical') {
            $form = $this->legacy->form($id);
            $form['api_mode'] = false;
            $form['api_writable'] = false;
            $form['api_editor_context'] = '';
            $form['api_creation_context'] = '';
            return $form;
        }
        $this->assertUpdateContractReady();
        if (!$this->eligible($details)) {
            throw new \LogicException('Cette facture API ne peut pas être modifiée par cet utilisateur Office dans le tenant courant.');
        }
        $form = $this->formFromDetails($details);
        $form['business_reference_prefix'] = $this->businessReferencePrefix($details->origin->sourceApp);
        $form['business_reference_source_label'] = match ($details->origin->sourceApp) {
            'devsys' => 'DevSys', 'spotcard' => 'SpotCard', 'stationid' => 'StationID', 'menuflow' => 'MenuFlow', default => 'source inconnue',
        };
        $form['business_reference_locked'] = $form['business_reference_prefix'] === null;
        $form['business_reference_pattern'] = $form['business_reference_prefix'] === null
            ? null
            : preg_quote($form['business_reference_prefix'], '/').'[A-Za-z0-9][A-Za-z0-9._/-]{0,95}';
        $form['api_mode'] = true;
        $form['api_writable'] = $this->enabled();
        $form['api_editor_context'] = $form['api_writable']
            ? $this->contexts->newEditorContext($id, (string) $details->etag, (int) $details->revision)
            : '';
        $form['api_creation_context'] = '';
        return $form;
    }

    /** @return array{id:int, api_mode:bool, editor_context?:string} */
    public function saveDraft(array $input, ?int $id): array
    {
        $this->rejectTenantSelectors($input);
        if ($id === null) {
            if (!$this->enabled()) {
                if (is_string($input['api_creation_context'] ?? null) && $input['api_creation_context'] !== '') {
                    throw new \LogicException('La création API a été désactivée depuis l’ouverture du formulaire. Aucune création SQL n’a été tentée. Rechargez le formulaire.');
                }
                return ['id' => $this->legacy->saveDraft($input), 'api_mode' => false];
            }
            $this->assertCreateContractReady();
            return $this->createApiDraft($input);
        }

        $details = $this->invoices->get($id);
        $this->assertRequestedId($details, $id);
        $origin = $this->origin($details);
        if ($origin === 'historical') {
            return ['id' => $this->legacy->saveDraft($input, $id), 'api_mode' => false];
        }
        if (!$this->enabled()) {
            throw new \LogicException('La modification API est désactivée. Aucune écriture SQL n’a été tentée.');
        }
        $this->assertUpdateContractReady();
        if (!$this->eligible($details)) {
            throw new \LogicException('L’API ne déclare pas ce brouillon modifiable par cet utilisateur Office. Rechargez-le.');
        }
        if (trim((string) ($input['internal_note'] ?? '')) !== '') {
            throw new \InvalidArgumentException('La note interne n’est pas prise en charge par les brouillons API. Effacez-la explicitement ou utilisez une facture historique.');
        }

        $token = $this->token($input['api_editor_context'] ?? null);
        $context = $this->contexts->get($token, 'edit', $id);
        if (($context['stale'] ?? false) === true) {
            throw new \LogicException('La facture a changé depuis son ouverture. Fermez et rouvrez le brouillon pour recharger sa version.');
        }
        $payload = $this->updatePayload($input, (string) $details->origin?->sourceApp);
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (is_array($context['pending_payload'] ?? null) && !hash_equals((string) $context['pending_payload']['hash'], $payloadHash)) {
            throw new \LogicException('Une sauvegarde API précédente est incertaine. Réessayez exactement les mêmes valeurs, ou rechargez le brouillon.');
        }
        $context['pending_payload'] ??= ['hash' => $payloadHash];
        $this->contexts->save($token, $context);
        $key = $this->contexts->updateKey($token, (string) $context['etag'], $payloadHash);

        try {
            $result = $this->invoices->updateDraft(
                $id,
                $this->updateDto($payload),
                $key,
                (string) $context['etag'],
            );
        } catch (\Throwable $exception) {
            if ($exception instanceof \Devsys\Shared\Api\Devsys\Exception\DevsysApiException
                && in_array($exception->getStatusCode(), [409, 412, 428], true)) {
                $context['stale'] = true;
                $this->contexts->save($token, $context);
            }
            throw $exception;
        }

        if ($result->replayed) {
            $confirmed = $this->invoices->get($id);
            $this->assertEditableDetails($confirmed, $id);
            $context['pending_payload'] = null;
            $context['stale'] = true;
            $this->contexts->save($token, $context);
            return ['id' => $id, 'api_mode' => true, 'reload_required' => true];
        } else {
            if (($result->invoice['id'] ?? null) !== $id || ($result->invoice['status'] ?? null) !== 'draft' || $result->sourceApp !== 'devsys') {
                throw new \InvalidArgumentException('La réponse de sauvegarde API ne correspond pas au brouillon attendu.');
            }
            $context['etag'] = $result->etag;
            $context['revision'] = $result->revision;
        }
        $context['pending_payload'] = null;
        $context['stale'] = true;
        $this->contexts->save($token, $context);

        return ['id' => $id, 'api_mode' => true, 'reload_required' => true];
    }

    public function issueDraft(array $input, ?int $id): string
    {
        $this->rejectTenantSelectors($input);
        if ($id === null || $id < 1) {
            return $this->legacy->issueDraft($input, $id);
        }
        $details = $this->invoices->get($id);
        $this->assertRequestedId($details, $id);
        if ($this->origin($details) !== 'historical') {
            throw new \LogicException('L’émission des brouillons API n’est pas raccordée. Aucune émission ni écriture SQL n’a été tentée.');
        }
        return $this->legacy->issueDraft($input, $id);
    }

    public function deleteDraft(int $id, array $input = []): void
    {
        $this->rejectTenantSelectors($input);
        $details = $this->invoices->get($id);
        $this->assertRequestedId($details, $id);
        if ($this->origin($details) !== 'historical') {
            throw new \LogicException('La suppression des brouillons API n’est pas disponible. Aucune suppression SQL n’a été tentée.');
        }
        $this->legacy->deleteDraft($id);
    }

    private function createApiDraft(array $input): array
    {
        $this->assertCreateContractReady();
        if (trim((string) ($input['internal_note'] ?? '')) !== '') {
            throw new \InvalidArgumentException('La note interne n’est pas prise en charge par les brouillons API. Effacez-la explicitement avant l’enregistrement.');
        }
        $token = $this->token($input['api_creation_context'] ?? null);
        $context = $this->contexts->get($token, 'create');
        $payload = $this->createPayload($input, (string) $context['source_event_id']);
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (is_array($context['payload'] ?? null) && !hash_equals((string) $context['payload']['hash'], $payloadHash)) {
            throw new \LogicException('Cette tentative de création est déjà liée à d’autres valeurs. Ouvrez un nouveau formulaire pour créer un nouveau brouillon.');
        }
        if (is_int($context['invoice_id'] ?? null)) {
            $id = $context['invoice_id'];
        } else {
            $context['payload'] = ['hash' => $payloadHash, 'data' => $payload];
            $this->contexts->save($token, $context);
            $result = $this->invoices->createDraft($this->createDto($payload), (string) $context['idempotency_key']);
            if ($result->sourceApp !== 'devsys' || $result->sourceEventId !== $context['source_event_id'] || ($result->invoice['status'] ?? null) !== 'draft') {
                throw new \InvalidArgumentException('La réponse de création API ne correspond pas à la tentative attendue.');
            }
            $id = (int) $result->invoice['id'];
            $context['invoice_id'] = $id;
            $this->contexts->save($token, $context);
        }

        $details = $this->invoices->get($id);
        $this->assertEditableDetails($details, $id);
        return [
            'id' => $id,
            'api_mode' => true,
            'reload_required' => true,
            'api_totals' => $this->apiTotals($details),
        ];
    }

    private function origin(InvoiceDetails $details): string
    {
        $this->assertSharedContractReady();
        if ($details->origin === null) {
            throw new \LogicException('L’API n’a pas fourni la provenance de cette facture. Aucune mutation Office n’est autorisée.');
        }
        if ($details->status !== 'draft' || $details->invoiceNumber !== null) {
            throw new \LogicException('Seuls les brouillons sans numéro peuvent être modifiés.');
        }
        if ($details->origin->kind === 'historical') {
            return 'historical';
        }
        return 'api/' . (string) $details->origin->sourceApp;
    }

    private function eligible(InvoiceDetails $details): bool
    {
        return $details->origin?->kind === 'api'
            && in_array($details->origin->sourceApp, ['devsys', 'spotcard', 'stationid', 'menuflow'], true)
            && $details->origin->apiUpdateEligibleForSource
            && $details->clientUuidProvided
            && $details->clientUuid !== null
            && $details->etag !== null
            && $details->revision !== null;
    }

    private function assertEditableDetails(InvoiceDetails $details, int $id): void
    {
        if ($details->id !== $id || $details->origin?->kind !== 'api' || !$this->eligible($details)) {
            throw new \LogicException('Le brouillon API n’est plus modifiable. Rechargez la liste des factures.');
        }
    }

    private function assertSharedContractReady(): void
    {
        foreach (['origin', 'clientUuid', 'clientUuidProvided'] as $property) {
            if (!property_exists(InvoiceDetails::class, $property)) {
                throw new \LogicException('Le SDK shared installé ne fournit pas les métadonnées de provenance et de client requises. Aucune mutation n’est autorisée.');
            }
        }
    }

    private function assertCreateContractReady(): void
    {
        $this->assertSharedContractReady();
        if (!property_exists(CreateInvoiceDraftRequest::class, 'businessReference')) {
            throw new \LogicException('Le SDK shared installé ne peut pas transmettre la référence métier à la création. Mettez-le à jour avant d’activer les brouillons API.');
        }
    }

    private function assertUpdateContractReady(): void
    {
        $this->assertSharedContractReady();
        if (!property_exists(InvoiceDetails::class, 'businessReference')
            || !property_exists(UpdateInvoiceDraftRequest::class, 'businessReference')
            || !method_exists(UpdateInvoiceDraftRequest::class, 'clearingBusinessReference')) {
            throw new \LogicException('Le SDK shared installé ne prend pas en charge la référence métier sans risque d’effacement implicite. Mettez-le à jour avant d’activer la modification des brouillons API.');
        }
    }

    private function assertRequestedId(InvoiceDetails $details, int $id): void
    {
        if ($details->id !== $id) {
            throw new \LogicException('La facture retournée par l’API ne correspond pas à la demande. Aucune mutation Office n’est autorisée.');
        }
    }

    private function formFromDetails(InvoiceDetails $details): array
    {
        $invoice = [
            'id' => $details->id,
            'invoice_number' => $details->invoiceNumber,
            'status' => $details->status,
            'issue_date' => $details->issueDate,
            'due_date' => $details->dueDate,
            'currency' => $details->currency ?? 'EUR',
            'client_uuid' => $details->clientUuid,
            'client_name' => $details->client->get('name'),
            'client_address_line1' => $details->client->get('address_line1'),
            'client_address_line2' => $details->client->get('address_line2'),
            'client_postal_code' => $details->client->get('postal_code'),
            'client_city' => $details->client->get('city'),
            'client_phone' => $details->client->get('phone'),
            'client_email' => $details->client->get('email'),
            'payment_terms_code' => $details->metadata['payment_terms_code'] ?? null,
            'payment_terms' => $details->metadata['payment_terms'] ?? null,
            'payment_method' => $details->metadata['payment_method'] ?? null,
            'public_note' => $details->metadata['public_note'] ?? null,
            'business_reference' => $details->businessReference,
            'internal_note' => '',
        ];
        $lines = array_map(static function (object $line): array {
            $values = $line->values;
            return [
                'label' => $values['label'],
                'description' => $values['description'] ?? '',
                'quantity' => $values['quantity'],
                'unit' => $values['unit'] ?? '',
                'unit_price_excl_tax' => $values['unit_price_excl_tax'],
                'discount_type' => $values['discount_type'] ?? '',
                'discount_value' => $values['discount_value'] ?? '0',
                'discount_note' => $values['discount_note'] ?? '',
                'tax_rate' => $values['tax_rate'],
            ];
        }, $details->lines);

        return [
            'invoice' => $invoice,
            'lines' => $lines,
            'api_amounts' => $this->apiTotals($details),
            'defaults' => ['tax_rate' => '20.00', 'currency' => $invoice['currency'], 'payment_terms' => null, 'payment_terms_code' => null, 'payment_method' => null],
        ];
    }

    private function apiTotals(InvoiceDetails $details): array
    {
        return [
            'subtotal_excl_tax' => $details->amounts->subtotalExclTax,
            'discount_total_excl_tax' => $details->amounts->discountTotalExclTax,
            'tax_total' => $details->amounts->taxTotal,
            'total_incl_tax' => $details->amounts->totalInclTax,
        ];
    }

    private function createPayload(array $input, string $sourceEventId): array
    {
        $base = $this->payloadFields($input);
        $base['business_reference'] = $this->businessReference($input['business_reference'] ?? null);
        return ['source_event_id' => $sourceEventId, 'source_app' => 'devsys', ...$base];
    }

    private function updatePayload(array $input, string $originSourceApp): array
    {
        $payload = ['source_app' => 'devsys', ...$this->payloadFields($input)];
        $intent = $input['business_reference_intent'] ?? (array_key_exists('business_reference', $input) ? 'set' : 'preserve');
        if (!is_string($intent) || !in_array($intent, ['preserve', 'set', 'clear'], true)) {
            throw new \InvalidArgumentException('L’intention de référence métier est invalide. Rechargez le brouillon.');
        }
        if ($intent === 'clear') {
            $payload['business_reference'] = null;
        } elseif ($intent === 'set') {
            $payload['business_reference'] = $this->businessReference($input['business_reference'] ?? null, false, $originSourceApp);
        }

        return $payload;
    }

    private function payloadFields(array $input): array
    {
        $clientUuid = trim((string) ($input['client_uuid'] ?? ''));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $clientUuid)) {
            throw new \InvalidArgumentException('Le client sélectionné est invalide.');
        }
        $date = (string) ($input['issue_date'] ?? '');
        $lines = $input['lines'] ?? [];
        if (!is_array($lines) || $lines === []) {
            throw new \InvalidArgumentException('Ajoutez au moins une ligne de facture valide.');
        }
        $mappedLines = [];
        foreach (array_values($lines) as $line) {
            if (!is_array($line)) throw new \InvalidArgumentException('Une ligne de facture est invalide.');
            $discountType = trim((string) ($line['discount_type'] ?? ''));
            $discountValue = trim((string) ($line['discount_value'] ?? ''));
            $mappedLines[] = [
                'label' => trim((string) ($line['label'] ?? '')),
                'description' => $this->nullable($line['description'] ?? null),
                'quantity' => $this->decimal($line['quantity'] ?? null, 'quantité'),
                'unit' => $this->nullable($line['unit'] ?? null),
                'unit_price_excl_tax' => $this->decimal($line['unit_price_excl_tax'] ?? null, 'prix unitaire'),
                'discount_type' => $discountType === '' ? null : $discountType,
                'discount_value' => $discountType === '' ? null : $this->decimal($discountValue, 'remise'),
                'discount_note' => $discountType === '' ? null : $this->nullable($line['discount_note'] ?? null),
                'tax_rate' => $this->decimal($line['tax_rate'] ?? null, 'taux de TVA'),
            ];
        }
        $dueDate = $this->nullable($input['due_date'] ?? null);
        if ($dueDate !== null && $dueDate < $date) {
            throw new \InvalidArgumentException('La date d’échéance ne peut pas être antérieure à la date de facture.');
        }

        return [
            'client_uuid' => strtolower($clientUuid),
            'issue_date' => $date,
            'due_date' => $dueDate,
            'lines' => $mappedLines,
            'payment_terms_code' => $this->nullable($input['payment_terms_code'] ?? null),
            'payment_terms' => $this->nullable($input['payment_terms'] ?? null),
            'payment_method' => $this->nullable($input['payment_method'] ?? null),
            'public_note' => $this->nullable($input['public_note'] ?? null),
        ];
    }

    private function createDto(array $payload): CreateInvoiceDraftRequest
    {
        return new CreateInvoiceDraftRequest(
            $payload['source_event_id'], $payload['client_uuid'], $payload['issue_date'], $this->lineDtos($payload['lines']),
            $payload['due_date'], $payload['payment_terms_code'], $payload['payment_terms'], $payload['payment_method'], $payload['public_note'], 'devsys', $payload['business_reference'],
        );
    }

    private function updateDto(array $payload): UpdateInvoiceDraftRequest
    {
        $request = new UpdateInvoiceDraftRequest(
            $payload['client_uuid'], $payload['issue_date'], $this->lineDtos($payload['lines']),
            $payload['due_date'], $payload['payment_terms_code'], $payload['payment_terms'], $payload['payment_method'], $payload['public_note'], 'devsys',
        );
        if (!array_key_exists('business_reference', $payload)) {
            return $request;
        }
        if ($payload['business_reference'] === null) {
            return UpdateInvoiceDraftRequest::clearingBusinessReference($request);
        }

        return new UpdateInvoiceDraftRequest(
            $payload['client_uuid'], $payload['issue_date'], $this->lineDtos($payload['lines']),
            $payload['due_date'], $payload['payment_terms_code'], $payload['payment_terms'], $payload['payment_method'], $payload['public_note'], 'devsys', $payload['business_reference'],
        );
    }

    private function businessReference(mixed $value, bool $nullable = true, string $sourceApp = 'devsys'): ?string
    {
        if ($value === null && $nullable) return null;
        $prefix = $this->businessReferencePrefix($sourceApp);
        if ($prefix === null) {
            throw new \InvalidArgumentException('Le format de référence métier de cette source n’est pas défini ; conservez la référence ou effacez-la explicitement.');
        }
        if (!is_string($value)) throw new \InvalidArgumentException('La référence métier ne respecte pas le préfixe de sa source d’origine.');
        $reference = strtoupper(trim($value));
        if (strlen($reference) > 100 || preg_match('/^'.preg_quote($prefix, '/').'[A-Z0-9][A-Z0-9._\/-]{0,95}$/D', $reference) !== 1) {
            throw new \InvalidArgumentException('La référence métier doit respecter le préfixe de sa source d’origine (100 caractères maximum). Pour la supprimer, cochez « Effacer la référence ».');
        }

        return $reference;
    }

    private function businessReferencePrefix(string $sourceApp): ?string
    {
        return match ($sourceApp) {
            'devsys' => 'DEV-',
            'spotcard' => 'SPC-',
            'stationid' => 'STI-',
            default => null,
        };
    }

    private function lineDtos(array $lines): array
    {
        return array_map(static fn (array $line): InvoiceDraftLine => new InvoiceDraftLine(
            $line['label'], $line['quantity'], $line['unit_price_excl_tax'], $line['tax_rate'],
            $line['description'], $line['unit'], $line['discount_type'], $line['discount_value'], $line['discount_note'],
        ), $lines);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_scalar($value)) throw new \InvalidArgumentException('Une valeur de facture est invalide.');
        $value = (string) $value;
        return trim($value) === '' ? null : $value;
    }

    private function decimal(mixed $value, string $label): string
    {
        if (!is_string($value) && !is_int($value)) throw new \InvalidArgumentException("Le champ {$label} doit être un décimal transmis comme chaîne.");
        $value = (string) $value;
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) throw new \InvalidArgumentException("Le champ {$label} est invalide.");
        return $value;
    }

    private function token(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) {
            throw new \LogicException('Le contexte du formulaire est absent ou invalide. Rechargez le brouillon.');
        }
        return $value;
    }

    private function rejectTenantSelectors(array $input): void
    {
        foreach (['tenant_id', 'tenant_uuid', 'issuer_id', 'invoice_number', 'source_app', 'source_event_id', 'idempotency_key'] as $field) {
            if (array_key_exists($field, $input)) throw new \InvalidArgumentException('Le formulaire contient un champ de sécurité interdit. Rechargez-le.');
        }
    }

    public function assertNoTenantSelectors(array $input): void
    {
        $this->rejectTenantSelectors($input);
    }
}
