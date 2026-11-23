# OFFICE-INVOICE-MVP-1 — Office API draft editing

Date: 2026-11-23 (Europe/Paris)

## Source and scope

- Repository: `lgimet/office`.
- Feature branch: `feature/office-invoice-mvp-1`, based directly on refreshed `main` `34646a5229f350c3bcd5a9fe120eb9e48d3840ea`.
- Implementation commit: `fe28d7bda82022f7f9f48a82077d36f9d9c4499d`.
- Draft PR: [#9](https://github.com/lgimet/office/pull/9), base `main`, state open/draft, GitHub reported mergeable/clean at report time; no CI checks were attached.
- Office `composer.lock` SHA-256: `a7a7a5941baca4d2b4e782894f5d8f5e2292a05b0f0374d291c1490a33991cc5`.
- The locked SDK and the physically installed SDK source both resolve to `b845450b2d4004fa31a5e3a540184fff42c7ebba`; no floating branch or mutable `../shared` path is used.
- Production code, flags, services, database and releases were not changed. No invoice form was submitted and no invoice mutation was sent.
- Read-only release pointers observed: API/login `api-invoice-draft-preflight-ttl-20261123-1623128`; Office `office-invoice-draft-preflight-ttl-20261123-5b3dcd8`. Vhost config and live HTTP behavior were not re-probed in this development task.

## Changes

- Office now accepts API-origin drafts from `devsys`, `spotcard`, `stationid` and `menuflow` only when the API detail response marks `api_update_eligible_for_source=true`; this is server-computed eligibility, not a browser selector.
- Cross-source drafts remain read-only unless that API capability is present. API update continues through `InvoiceDraftApiInterface` / the installed SDK with Office as editor source, opening ETag, idempotency context and retry payload protection.
- API-owned drafts never fall back to the legacy SQL service when disabled, stale, missing provenance, or on API failure. The existing SQL behavior for historical invoices remains unchanged. Issue and delete stay unavailable for API invoices.
- Existing source business references are displayed with their origin label. Office permits the known `SPC-` and `STI-` forms for their respective origins, preserves omission, and supports explicit clearing. MenuFlow has no defined prefix: its existing reference is read-only in the form; users may preserve or explicitly clear it. No prefix was invented.
- Added tests for denied foreign-source form, authorized Office admin update without origin spoofing or SQL fallback, reference preservation and replacement, and retry behavior.

## Validation

- `composer test`: PASS — 167 tests, 487 assertions. (Initial run before the final replacement test was 166 tests / 484 assertions.)
- `composer validate --no-check-publish`: PASS with existing missing-license warning.
- `composer check-platform-reqs --no-dev`: PASS on PHP 8.4.24.
- `composer audit --locked --no-dev`: PASS, no production advisories.
- `npm ci`: PASS; 18 packages audited, no vulnerabilities.
- `npm run build`: PASS (Sass build; no tracked generated CSS changed).
- `node --check public/assets/js/Object/Invoices.js`: PASS.
- PHP lint and `git diff --check`: PASS for modified files.

The SDK vendor tree used for these tests was copied from a separate Office worktree with the same composer.lock hash; its Git HEAD was verified against the locked commit above. This was test setup only and changed no tracked dependency files.

## Limitations and deployment

The cross-source policy relies on the existing API tenant membership roles `owner`/`admin`, validated server-side by `RequireTenantContextMiddleware`. The grant is separate from `write:invoices`; review the intended meaning of these existing roles before merge. No live session, client data or tenant was inspected. API and Office runtime release pointers were not changed in this development worktree; no production probe was performed.

No shared change is needed. API and Office changes are on separate branches based directly on refreshed `main`; there has been no merge or deployment. The API integration test is not a production test and requires an isolated DSN to run. Draft writes and all issuance/deletion remain outside this lot's execution.

Status: `PARTIAL_READY` — Office UI/service and API contract changes are implemented and unit/build tested; API isolated-DB integration and review/merge remain outstanding.
