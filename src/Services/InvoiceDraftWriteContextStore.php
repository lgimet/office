<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Oidc\OidcSessionService;

/** Server-side, session-scoped retry and editor state. No invoice is persisted here. */
final class InvoiceDraftWriteContextStore
{
    private const SESSION_KEY = 'office_invoice_draft_write_contexts';

    public function __construct(private readonly OidcSessionService $sessions)
    {
    }

    public function newCreateContext(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->put($token, [
            'type' => 'create',
            'created_at' => time(),
            'source_event_id' => 'office-' . bin2hex(random_bytes(24)),
            'idempotency_key' => 'office-create-' . bin2hex(random_bytes(32)),
            'payload' => null,
            'invoice_id' => null,
        ]);

        return $token;
    }

    public function newEditorContext(int $invoiceId, string $etag, int $revision): string
    {
        if ($invoiceId < 1 || preg_match('/^"[1-9][0-9]*"$/D', $etag) !== 1 || $revision < 1 || $etag !== '"' . $revision . '"') {
            throw new \InvalidArgumentException('La version du brouillon API est invalide. Rechargez le formulaire.');
        }
        $token = bin2hex(random_bytes(32));
        $this->put($token, [
            'type' => 'edit',
            'created_at' => time(),
            'invoice_id' => $invoiceId,
            'etag' => $etag,
            'revision' => $revision,
            'pending_payload' => null,
            'stale' => false,
        ]);

        return $token;
    }

    /** Reuse one session-bound idempotency key for this invoice across retries and form reloads. */
    public function newIssueContext(int $invoiceId): string
    {
        if ($invoiceId < 1) throw new \InvalidArgumentException('La facture demandée est invalide.');
        $owner = $this->owner();
        foreach ($this->all() as $token => $context) {
            if (($context['type'] ?? null) === 'issue'
                && ($context['invoice_id'] ?? null) === $invoiceId
                && hash_equals((string) ($context['owner'] ?? ''), $owner)
                && is_string($context['idempotency_key'] ?? null)) {
                return (string) $token;
            }
        }

        $token = bin2hex(random_bytes(32));
        $this->put($token, [
            'type' => 'issue',
            'created_at' => time(),
            'invoice_id' => $invoiceId,
            'idempotency_key' => 'office-issue-' . bin2hex(random_bytes(32)),
        ]);

        return $token;
    }

    public function issueKey(string $token, int $invoiceId): string
    {
        $context = $this->get($token, 'issue', $invoiceId);
        $key = $context['idempotency_key'] ?? null;
        if (!is_string($key) || preg_match('/^office-issue-[a-f0-9]{64}$/D', $key) !== 1) {
            throw new \LogicException('La tentative d’émission a expiré. Rechargez la facture.');
        }

        return $key;
    }

    /** @return array<string, mixed> */
    public function get(string $token, string $type, ?int $invoiceId = null): array
    {
        $context = $this->all()[$token] ?? null;
        if (!is_array($context)
            || !hash_equals((string) ($context['owner'] ?? ''), $this->owner())
            || ($context['type'] ?? null) !== $type
            || ($invoiceId !== null && ($context['invoice_id'] ?? null) !== $invoiceId)) {
            throw new \LogicException('Le contexte du formulaire a expiré ou ne correspond pas à cette facture. Rechargez-la.');
        }

        return $context;
    }

    public function save(string $token, array $context): void
    {
        $current = $this->get($token, (string) ($context['type'] ?? ''));
        if (isset($current['invoice_id']) && ($context['invoice_id'] ?? null) !== $current['invoice_id']) {
            throw new \LogicException('Le contexte ne peut pas être associé à une autre facture.');
        }
        $context['owner'] = $current['owner'];
        $context['updated_at'] = time();
        $all = $this->all();
        $all[$token] = $context;
        $_SESSION[self::SESSION_KEY] = $all;
    }

    /** Stable for the same editor, ETag and payload even if a request dies before PHP saves session changes. */
    public function updateKey(string $token, string $etag, string $payloadHash): string
    {
        return 'office-update-' . hash_hmac('sha256', $this->owner() . '|' . $token . '|' . $etag . '|' . $payloadHash, session_id());
    }

    private function put(string $token, array $context): void
    {
        $context['owner'] = $this->owner();
        $context['created_at'] = time();
        $all = $this->all();
        $all[$token] = $context;
        $_SESSION[self::SESSION_KEY] = $all;
    }

    private function all(): array
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            throw new \LogicException('La session Office est indisponible. Reconnectez-vous.');
        }

        $contexts = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($contexts)) return [];
        $contexts = array_filter($contexts, static fn ($context): bool => is_array($context)
            && is_int($context['created_at'] ?? null)
            && ($context['created_at'] + 86400) >= time());
        if (count($contexts) > 100) {
            uasort($contexts, static fn (array $left, array $right): int => ($right['updated_at'] ?? $right['created_at']) <=> ($left['updated_at'] ?? $left['created_at']));
            $contexts = array_slice($contexts, 0, 100, true);
        }
        $_SESSION[self::SESSION_KEY] = $contexts;
        return $contexts;
    }

    private function owner(): string
    {
        $identity = $this->sessions->identity();
        $tenant = is_array($identity) ? (string) ($identity['tenant_uuid'] ?? '') : '';
        $user = is_array($identity) ? (string) ($identity['user_uuid'] ?? '') : '';
        if ($tenant === '' || $user === '' || session_id() === '') {
            throw new \LogicException('La session utilisateur ou le tenant Office est indisponible. Reconnectez-vous.');
        }

        return hash('sha256', session_id() . '|' . strtolower($tenant) . '|' . strtolower($user));
    }
}
