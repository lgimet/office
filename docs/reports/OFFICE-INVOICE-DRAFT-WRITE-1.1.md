# OFFICE-INVOICE-DRAFT-WRITE-1.1

**Date:** 2026-11-23 (Europe/Paris)

**Decision:** `AWAITING_OPERATOR_SERVER_PROOF`
**Mutating recipe:** not authorized or executed; all invoice write flags remain OFF.

## Operator declaration

The operator declared the manual authenticated Office checks `PASS`: OIDC login, dashboard, invoice list, client search, and an existing authorized fictional client. The operator stated that no business write occurred. This is recorded as a **manual operator declaration**, not an instrumented HTTP trace and not proof of API request attributes. No client, user, or tenant identifier is included.

## Sources and active releases checked

- Office `work/office-invoice-draft-write-1` base: `76549a3b6726f8eb9a974bed9c5821b9993348b4`; new branch `work/office-invoice-draft-write-1.1` is based on it. Office runtime remains `office-invoice-web-20261123-64b6b29-0661b92`.
- Office active feature source: `feature/office-inv-recette-1.2` `64b6b298c6dc049c06b671e02e813b93255da090`; `office/main` `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`.
- API runtime remains `api-invoice-draft-guard-20261123-aa9be47-r1`; deployed source/report branch `work/office-api-guard-integration-1` `a07573566f2f08986afe70f722c714a0615a046c`; API `main` `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf`.
- Shared SDK in Office vendor: `0661b92b914c28c41f45f5bd19bb9a860c232676`; shared `main` `83e5b7da32ae20039218586e18dc93ee9ad95852`.

## Identity proof table

| Proof item | Status | Finding |
|---|---|---|
| Invoice bearer origin | `PASS` static review | Office invoice services use `OfficeAccessTokenProvider`, which returns/refreshes the token held in the OIDC session; `DevsysApiProvider` supplies it to shared `DevsysApiClient`; invoice adapters call shared `InvoicesApi`. `DevsysOAuthTokenProvider` is not the invoice bearer source. Token content was not inspected. |
| Office user/tenant source | `PASS` static review | `OidcSessionService` stores identity in server session. `TenantContext` resolves the session tenant/user through the membership repository. No browser-supplied tenant selector is an authority. |
| API request principal/tenant/source | `PASS` design; actual operator request `NOT_RUN` | API `RequireInvoiceAccessMiddleware` builds `effective_tenant_id` and `invoice_source_app` from the authenticated User membership. Recipe guard compares server attributes; no request body, query, or header is used as identity. |
| Actual principal subject and effective tenant match the guard allowlist | `NOT_RUN` | Guard allowlist remains absent. The manual login declaration cannot attest the API-side attributes of the invoice bearer. No existing diagnostic route returns a redacted match-only result. |
| Actual API `write:invoices` scope acceptance | `NOT_RUN` | Read flows do not establish the write scope; no mutation or scope change was attempted. |
| Existing fictional client | `PASS — operator declaration` | No record/UUID or personal data was inspected. |
| Guard behavior | `PASS` isolated unit suite | The deployed code accepts only the configured User/tenant/server-source combination for create/update and denies issue while ON. Existing auth, scope, invoice access, and global gate remain in order. |

The minimal safe proof is a separately reviewed, temporary authenticated API GET preflight that uses the same bearer and scope + invoice access middleware as write routes, and returns only booleans for type, scope, subject match, effective tenant match, and source match. It must not return identities. It has not been implemented or deployed. A private server-side operator procedure or such a diagnostic, after its own approval, is required; do not send cookies or bearer tokens to Codex.

## Watchdog preparation

A dedicated API branch `work/office-invoice-draft-watchdog-1` at `5efd44be4c7f94e9f7e8356aa94527be989f1985` is based on the deployed guard integration and contains the proposed watchdog, sandbox tests, and operator note. Its purpose is to ensure the future open command cannot run without a previously armed/verifiable systemd expiry timer. It constrains the opening to API guard ON, global API source `devsys`, and Office flag ON. Its close path sets Office OFF first, closes the API gate and source list, turns the guard OFF, clears private guard identity, and verifies process-level overrides. It never calls invoice routes.

The watchdog was **not installed or armed**. Its 13 Python sandbox tests pass using dummy env files and fake systemd responses. This tests atomic targeted updates, checksum race protection, identity/config refusal, opening refusal without a timer, timer deadline bounds, second-window refusal, simulated expiry, and retry after an injected write failure. It does not validate a real systemd timer, host restart, or live PHP-FPM environment transition.

### Exact configuration blocker

The active API dotenv path resolves through the release symlink to `/home/devsys/api/.env`; metadata-only inspection shows mode `0644`, owner/group `devsys:www-data`. The watchdog rejects `verify`/`arm`/`open` while the API config is world-readable. This prevents storing the private subject/tenant values safely. No permission or file content was changed. Office `.env` is mode `0640 devsys:www-data`.

The running PHP-FPM process environments inspected contain no targeted write/guard overrides (19 processes); systemd unit environments have none. No FPM reload was attempted. The watchdog can refuse a detected conflicting override, but actual runtime behavior after a real closure is `NOT_RUN`.

## Validation executed

| Check | Result |
|---|---|
| Watchdog Python sandbox | `PASS` — 13 tests. |
| Python compile / CLI help | `PASS`. |
| Deployed guard PHPUnit baseline in isolated worktree | `PASS` — 269 tests, 876 assertions, 16 skipped; 15 MySQL cases lack an isolated DSN and one SQLite case lacks PDO SQLite. |
| `composer test` in new API script-only worktree | `NOT_RUN` — attempted but PHPUnit is not installed there; the unchanged API guard source/test baseline was run in its isolated parent worktree. No composer install was needed for Python-only changes. |
| PHP lint | `PASS` for the deployed guard middleware; no PHP file changed. |
| Watchdog runtime `verify --dry-run` against active path | `EXPECTED_REFUSAL` — active API `.env` is world-readable. No file was changed. |
| Real systemd timer, FPM runtime close, reboot recovery | `NOT_RUN`. |
| Office composer / npm build | `NOT_RUN` — Office source/assets are unchanged; no production build was needed. |
| `git diff --check` | `PASS` after final report edits in both branches. |

## Blockers and required next action

1. `AWAITING_OPERATOR_SERVER_PROOF`: establish through the operator's authenticated Office session, server-side and without disclosure, the actual API User principal, effective tenant, source and accepted `write:invoices` scope. Current code has no match-only diagnostic, so this requires a small reviewed temporary GET diagnostic or a pre-existing approved server procedure.
2. Private API guard identity cannot be staged while its active env target is world-readable. Obtain a separate GO for the minimal permission hardening or an approved private config source; do not change it in this lot.
3. The watchdog is sandbox-tested but not installed. A separate install GO is required for its exact ops path and transient systemd operation; test actual timer expiry/close and explicitly decide how to handle host restart before any write window.
4. Obtain operator confirmation that no other consumer depends on invoice writes during the future recipe window. The guard blocks other identities/sources and all issue operations while active.

Flags remain OFF: Office draft write is `false`; API guard/global gate are absent and default OFF, source list empty. Migration 024 was not queried or rerun. No client/facture write, SQL operation, permission change, config edit, systemd change, scope change, or deployment occurred.

**Final status: `AWAITING_OPERATOR_SERVER_PROOF`.** No mutation GO is requested or inferred by this report.
