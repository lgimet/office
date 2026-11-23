# OFFICE-INVOICE-DRAFT-PREFLIGHT-GO-2-TTL-1

Date: 2026-11-23 (Europe/Paris)  
Status: `TTL_DEPLOYED_DIAGNOSTIC_OFF` — Office TTL enforcement was deployed after a separate operational GO. This does **not** authorize diagnostic activation.

## Source and runtime references

Work was prepared in isolated branch `work/office-invoice-draft-preflight-go-2-ttl-1`, based on `work/office-invoice-draft-preflight-go-2` at `93f4df52d79ba43f42a9ce7ccd05cad1f7d389c8`. Fetched Office `origin/main` was `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`.

The Office lock remains pinned to `devsys/shared` commit `b845450b2d4004fa31a5e3a540184fff42c7ebba`; shared was not changed. The last read-only runtime check found Office release `office-invoice-draft-preflight-20261123-7badc6c` and API/login release `api-invoice-draft-preflight-20261123-f24c4e3`. No production release, config, `.env`, vhost, service, or flag changed. No operator session or token was accessed and no diagnostic request was made.

## Change

Added `OFFICE_INVOICE_PREFLIGHT_STARTED_AT`, `OFFICE_INVOICE_PREFLIGHT_EXPIRES_AT`, and `OFFICE_INVOICE_PREFLIGHT_BOOT_ID` to the temporary diagnostic configuration example and enforced them in `OfficeInvoiceRecipePreflightService` before session identity lookup and before the SDK request.

The values use canonical unsigned Unix seconds, with a fixed interval of at most 300 seconds. A request is permitted only when `started_at <= now < expires_at`, the configured boot UUID matches the current kernel boot ID, the diagnostic enable flag and server-side operator allowlist are valid, and the current session matches the existing server-side check. Missing, malformed, duplicate, overflowing, expired, future-started, wrong-boot, unreadable dotenv, or clock-error cases fail closed. The absolute deadline is not extended by requests. On expiry the service returns its existing unavailable result and does not call the API SDK.

| Condition | Before | After |
|---|---|---|
| Diagnostic OFF | No diagnostic request | Same |
| Diagnostic ON, no valid lease | Could reach session/API checks | Unavailable before session lookup and SDK call |
| Valid lease before deadline on same boot | Existing operator/session checks | Existing checks plus lease validation |
| At or after deadline / after reboot | No expiry barrier | Unavailable, no API call |

The TTL does not erase private configuration. It is not a substitute for independent cleanup.

## Files changed

`.env.example`, `src/Services/OfficeInvoiceRecipePreflightService.php`, and `tests/Unit/OfficeInvoiceRecipePreflightServiceTest.php`. No controller, routing, CSRF, SDK, frontend, invoice write path, SQL fallback, or OIDC implementation changed.

## Tests and limits

- Office `composer test`: PASS, 164 tests / 470 assertions.
- `composer validate --no-check-publish`: PASS (existing missing-license warning).
- `composer check-platform-reqs --no-dev`: PASS, PHP 8.4.24.
- `composer audit --locked --no-dev`: PASS, no production advisories.
- PHP lint on changed PHP: PASS.
- `git diff --check`: PASS before report creation; rerun after report creation and commit.
- Composer initially could not fetch the pinned private SDK dist archive. The exact lock-pinned commit was verified in the isolated vendor tree; install from the lock with source preference completed without changing package versions. No `composer update` was run.
- `npm run build`: N/A; frontend assets unchanged.
- Authenticated operator diagnostic, production FPM expiry, reboot, and systemd cleanup: `NOT_RUN`.

## Operational closure boundary

The API middleware independently rejects the diagnostic after expiry and after a host reboot due to the boot-ID binding. Office also refuses and avoids the SDK call. This prevents diagnostic success if flags linger, but does not remove the private expectations or reset the flags.

Independent cleanup remains a separate operations task: root-owned helper, private lease state, exclusive lock, persistent absolute-expiry systemd timer plus boot recovery, idempotent Office-first then API close, targeted deletion of diagnostic-only values, no Apache dependency or FPM restart, and failure reporting through existing monitoring. It has not been installed or tested on the host. Therefore production diagnostic reactivation remains prohibited until separately authorized and validated.

No invoice form was submitted; the historical SQL fallback was not exercised. All diagnostic and invoice-write flags remained OFF/absent as observed. No session, diagnostic POST, production SQL, or migration was used.

## TTL deployment — 2026-11-23

The operator separately authorized deployment of the TTL code with diagnostics and invoice writes OFF. Office was assembled from the complete active release `office-invoice-draft-preflight-20261123-7badc6c`; only `.env.example` and `src/Services/OfficeInvoiceRecipePreflightService.php` were overlaid. Tests remain in source and were not added to the runtime release. The archive is `/home/devsys/office-deploy/artifacts/office-invoice-draft-preflight-ttl-20261123-5b3dcd8.tar.gz`, SHA-256 `672624e52c7a776a6c496e527f49b3acb18ecd6456c6b7a1e88119d78537192c`. `composer.lock` remains SHA-256 `a7a7a5941baca4d2b4e782894f5d8f5e2292a05b0f0374d291c1490a33991cc5`; runtime `devsys/shared` is still exact commit `b845450b2d4004fa31a5e3a540184fff42c7ebba` and JWT remains v7.2.2.

At 12:52 CET, the `office.devsys.fr` vhost was backed up, rendered with exactly four absolute `DocumentRoot`/`<Directory>` changes, installed atomically, and followed by a successful Apache configtest and one graceful Apache reload. The `current` marker now points to `/home/devsys/office-deploy/releases/office-invoice-draft-preflight-ttl-20261123-5b3dcd8`. Fresh backup: `/home/devsys/office-deploy/backups/office-invoice-draft-preflight-ttl-20261123-5b3dcd8-pre-switch/`; backup checksum passed. Previous Office release remains intact for rollback. PHP-FPM was not reloaded or restarted.

API/login was switched first and now points to `api-invoice-draft-preflight-ttl-20261123-1623128`, archive SHA-256 `414381f9e655c865ffcab94cf38696df6342c915101abb763cee55c13189f29a`; its API/login backup ID is `vhost-20261123T125036-1137302-031754478-api-invoice-draft-preflight-ttl-20261123-1623128`. The API manifest, lock, JWT version, external dotenv/storage links, and four-vhost backup checks passed.

Read-only post-switch results: Office home 302; invoice JS and dashboard CSS 200 with hashes unchanged from the old release; `/Invoices/data` 401 without a session; `GET /Invoices/recipePreflight` 405 (no POST sent). API health, OIDC discovery, JWKS and login were 200; invoice collection without bearer was 401; API diagnostic path was 404 with `no-store`; that path on login was 404. Apache configtest, PHP-FPM configuration test, and active service checks passed.

The active Office `.env` remained `0640 devsys:www-data`, readable by `www-data`; API dotenv resolves through the deployment symlink to `/home/devsys/api/.env`, also `0640 devsys:www-data`. The Office draft-write flag remained false; Office/API diagnostic flags and private expectation/lease keys were absent. API invoice write and recette flags remained absent/OFF, with source allowlist empty/absent. No named overrides were found in visible PHP-FPM process environments or static pool/service configuration.

Tests rerun before deployment: Office 164 tests / 470 assertions; API 307 / 947 with 16 SQL-dependent skipped. Composer validation, platform checks, audits, PHP lint, release hashes, and `git diff --check` passed. `npm run build` is N/A because no frontend assets changed. The archive contains the external `.env` symlink only, not dotenv contents.

No authenticated diagnostic was run and no live expiry test was attempted because the diagnostic stayed OFF. No cleanup timer/service was installed. The TTL checks are deployed but do not erase private configuration or reset flags. A separate GO is still required for independent cleanup installation/testing, followed by a distinct diagnostic activation GO. Invoice mutation remains outside this deployment.

Final status: `TTL_DEPLOYED_DIAGNOSTIC_OFF`.
