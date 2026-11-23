# OFFICE-INVOICE-DRAFT-PREFLIGHT-GO-2-ACTIVATE — Office

Date: 2026-11-23, Europe/Paris
Decision: **BLOCKED_EXPIRY_NOT_FAIL_CLOSED**. No diagnostic activation, no authenticated diagnostic call, and no invoice write occurred.

## References and active release

- Office report branch HEAD before this report: `work/office-invoice-draft-preflight-go-2`, `f7cbf5194a3121158bfbbedcec1bbe0ce6bd3f52`; Office `main`: `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`.
- Office release: `office-invoice-draft-preflight-20261123-7badc6c`; archive SHA-256 `10434241c4c5346c87c8c616a5b01a023140324e33518dfb875f84b3176ce99c`.
- SDK installed from locked commit `b845450b2d4004fa31a5e3a540184fff42c7ebba`; no SDK/package change was made.
- API/login release: `api-invoice-draft-preflight-20261123-f24c4e3`; archive SHA-256 `f191ca8afa4fe920ed4fe836943405263457dc7b0a4052f95330a693648f27a5`.

## Read-only preflight

- API dotenv resolves to `/home/devsys/api/.env`, mode `0640`, owner/group `devsys:www-data`; Office dotenv is `/home/devsys/office/.env`, also `0640 devsys:www-data`. Both are readable by `www-data`. The earlier GO-1 content-hash proof remains unverified; no retroactive claim is made.
- `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`; `OFFICE_INVOICE_PREFLIGHT_ENABLED` absent. API write/guard/diagnostic flags and source allowlist are absent/OFF. No named overrides appeared in the current readable FPM worker snapshot.
- API health, OIDC discovery, JWKS and login returned 200; API invoice collection returned 401; API diagnostic path returned 404 on API and login; Office home returned 302 and invoice data without a session returned 401.
- The diagnostic action is POST-only. No POST was sent and no operator session, CSRF token, bearer or cookie was read or used.

## Expiration gate — failed

`OfficeInvoiceRecipePreflightService` checks the OFF-by-default flag and the configured server-side operator subject; it has no absolute lease deadline. The API middleware likewise has no per-request expiry check. No diagnostic-specific persistent timer/service is installed. A timer that only closes flags cannot guarantee refusal after reboot if it is lost; no application deadline currently closes that gap. No write or diagnostic flag was enabled.

Because the API expiry gate fails, this report does not claim the operator identity, tenant, or scope was verified. These values remain unconfigured and undisclosed.

## Tests and next step

No code changed in this activation attempt; `composer test`, `npm run build`, PHP lint and SQL tests were not rerun. The earlier isolated test results are in the deployment report and do not prove expiry. Only anonymous GET checks were performed.

A separate targeted correction must add a server-side absolute deadline check to API and Office diagnostics and a reboot-safe independent closure mechanism, then test expiry/crash/reboot before a new deployment GO. Until then, preserve all diagnostic flags OFF. No invoice create/PATCH/issue/delete is authorized here.
