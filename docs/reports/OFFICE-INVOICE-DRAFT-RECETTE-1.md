# OFFICE-INVOICE-DRAFT-RECETTE-1

**Date:** 2026-11-23 (Europe/Paris)  
**Read-only outcome:** `READ_ONLY_PARTIAL`  
**Mutating phase:** `AWAITING_MUTATION_GO` — currently unsafe to enable; the active API release has no identity/tenant recipe guard.

## References and active releases

| Component | Reference checked | Runtime state |
|---|---|---|
| Office `main` | `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` | Not the deployed source. |
| Office feature | `64b6b298c6dc049c06b671e02e813b93255da090` | Source of deployed Office candidate. |
| Office deployment report branch | `work/office-invoice-web-deploy-1`, `ccbba1150bf5769a84b522e056c417e7b7c34f70` | Latest remote head checked at start of this task. |
| Office active release | `office-invoice-web-20261123-64b6b29-0661b92` | `/home/devsys/office-deploy/current` resolves to this release. |
| Shared SDK | `0661b92b914c28c41f45f5bd19bb9a860c232676` | Locked and installed in Office release as a real directory, not a mutable symlink. |
| API active release | `api-invoice-compat-20261123-ce80025-r2` | `/home/devsys/api-deploy/current` resolves to this release. |
| API deployment report | `work/office-api-release-switch-1`, `60a913808b47ab31a9c9700d37f6343799adcf2a` | Prior report records API/login deployment and read-only checks. |

No application source was changed in this task. This report is on `work/office-invoice-draft-recette-1`, based on the deployed Office branch lineage at `ccbba1150bf5769a84b522e056c417e7b7c34f70`.

## Read-only checks performed

Checks were made on 2026-11-23 around 05:03 Europe/Paris. Requests were HTTPS GETs pinned to loopback with the expected Host/SNI. No cookies, bearer tokens, POSTs, or form submissions were used. Response bodies containing account or business data were not recorded.

| Request | Result | What it establishes |
|---|---:|---|
| `office.devsys.fr/` | 302 | Existing unauthenticated redirect path responds. |
| `office.devsys.fr/dashboard` | 302 | Dashboard requires the existing login flow. |
| `office.devsys.fr/Invoices/data` | 401 | GET invoice data is protected without a session. |
| `office.devsys.fr/Invoices/clientOptions` | 401 | Client search endpoint is protected without a session. |
| `office.devsys.fr/Invoices/clientDetails` | 401 | Client detail endpoint is protected without a session. |
| `api.devsys.fr/api/v1/health` | 200 | API public health responds. |
| `login.devsys.fr/.well-known/openid-configuration` | 200 | OIDC discovery responds. |

The Office active release path and API release path were re-resolved and matched the expected release IDs. Apache vhost checksum remained `7e43eb0b0596f09f0a5d3fb2b029189193c4ca419377fc67c85f68fb07305c59`. A ten-minute aggregate of Office access logs contained 9 requests (5×200, 1×302, 3×401) and 0×5xx. No raw log lines were copied. The earlier deployment report records SpotCard root as 404 and MenuFlow root as 200; no additional witness probe was needed in this read-only task.

### Authentication and UI

No legitimate operator browser session was supplied. Therefore the following remain `NOT_RUN`: interactive OIDC login, authenticated dashboard, invoice list, client search, invoice detail, and loading the invoice form. No existing invoice or client records were inspected. No claim of successful authenticated Office→SDK→API invoice reading is made.

The invoice list page action `Invoices::list` is a protected **POST**, despite rendering a view. It was not called: no authenticated session/CSRF context was available, and no POST was needed for this phase. `Invoices::data`, `clientOptions`, and `clientDetails` are protected GET actions; anonymous requests returned 401. Save, issue, and delete routes are POST and were not called.

## Flags and write-path safety

The Office external `.env` was inspected by extracting only the named flags; `public/index.php` loads it through `Dotenv::createImmutable()`. It contains:

- `OFFICE_INVOICE_READ_API_ENABLED=true`
- `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`

No matching override was found in Apache, the PHP-FPM pool, or its systemd drop-in configuration. The Office code reads the draft flag from `$_ENV` and defaults to `false`. The API external environment has no `INVOICE_WRITES_ENABLED`, `INVOICE_WRITE_SOURCES`, or recipe-guard value; active API bootstrap defaults the global gate to `false` and parses an absent source allowlist as empty. These are configuration-derived effective inputs; no diagnostic endpoint or live worker instrumentation was used.

**Important:** the Office create path is not safe to probe while the Office write flag is OFF. In the deployed `DevsysInvoiceDraftService::saveDraft()`, a new create with the API flag disabled can call `legacy->saveDraft()` and write the historical Office SQL path. Existing historical invoice edits also have a legacy path. Accordingly, no form submission or mutation-refusal probe was attempted. In API mode, the service's API error handling explicitly avoids SQL fallback for API-owned draft writes, but that behavior was not exercised over production HTTP.

The active API release does **not** contain `InvoiceDraftRecetteGuardMiddleware` or `INVOICE_DRAFT_RECETTE_GUARD_ENABLED` handling. Since the API write gate is global and an allowlist for `devsys` alone does not confine writes to one operator and server-selected tenant, enabling the API and Office flags for a live recipe is not currently safe. Do not approve a mutating window against this runtime. A targeted API guard release, OFF by default and fail-closed to the approved server principal plus tenant/source, must be deployed and verified in a separate authorized step before considering a draft recipe.

## Phase B proposal — not executed

No draft was created, patched, issued, numbered, or deleted. No SQL or migration was run. A future recipe request should be a separate GO that specifies:

1. The API release containing the tested server-side recipe guard, its immutable SHA, and the exact active Office/API release IDs.
2. The approved operator identity and the tenant resolved server-side for that session; no browser-supplied tenant, issuer, or source values.
3. A short window, one already-existing authorized fictional client, and the exact fictional draft payload/reference. Do not create a client as part of this test.
4. The initial and final Office/API flags, who may change them, any necessary service reload explicitly authorized, and an automatic/manual deadline to restore flags OFF even on failure.
5. Permission limited to one draft create and specifically enumerated PATCH operations; no issue/delete, invoice number, PDF, mail, payment, export, or external integration.
6. Retention of the test draft for later authorized cleanup. No delete should be improvised because API draft deletion is not part of the recipe.

Before such a GO, first deploy the narrow API guard with all write flags OFF; verify refusals for issue and non-approved principal/tenant/source through isolated tests; confirm the Office API create path cannot fall back to SQL after an API failure; then review the proposed identity, tenant, client, and rollback. This task did not activate any flag, alter scope/OIDC, access SQL, or perform a mutation.

## Manual authenticated read-only checklist

With an operator-controlled browser session only:

1. Sign in through the existing Office OIDC flow; do not share cookies or tokens with Codex.
2. Open Dashboard, Factures, and client search; confirm the expected tenant context and note only pass/fail, not names or amounts.
3. If a pre-existing invoice is available and authorized, open its detail read-only. If none exists, record `NO_EXISTING_INVOICE`; do not create one.
4. Open the invoice form only for visual inspection and do not submit it. Do not test write refusal by clicking Save, Issue, or Delete.
5. Report any 5xx/OIDC issue by timestamp and route class only, with secrets and business data omitted.

**Final:** `READ_ONLY_PARTIAL` / `AWAITING_MUTATION_GO`. Anonymous routing and API/OIDC public checks passed; authenticated application behavior was unavailable, and live mutation remains blocked by the missing active server-side recipe guard plus the known Office legacy SQL fallback when its API draft flag is OFF.

## Validation activity

No PHP, JavaScript, CSS, Composer, or runtime code was changed. Therefore `composer test`, `npm run build`, and `php -l` were not rerun for this documentation-only report. `git diff --check` is run before committing the report.
