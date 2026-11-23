# OFFICE-INVOICE-DRAFT-PREFLIGHT-GO-2-TTL-1

Date: 2026-11-23 (Europe/Paris)  
Status: `READY_FOR_TTL_DEPLOY_GO` — Office-side expiry enforcement and isolated tests are ready for a separate deployment decision. This does **not** authorize diagnostic activation.

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

No invoice form was submitted; the historical SQL fallback was not exercised. All diagnostic and invoice-write flags remained OFF/absent as observed. No production write, deployment, systemd change, SQL, or migration was performed.
