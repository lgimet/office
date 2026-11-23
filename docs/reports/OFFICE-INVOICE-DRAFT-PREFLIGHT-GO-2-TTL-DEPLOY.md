# OFFICE-INVOICE-DRAFT-PREFLIGHT-GO-2-TTL-DEPLOY — Office

Status: `TTL_DEPLOYED_DIAGNOSTIC_OFF`
Operation: 2026-11-23, approximately 12:52 Europe/Paris.

Office now uses immutable release `office-invoice-draft-preflight-ttl-20261123-5b3dcd8`, sourced from `work/office-invoice-draft-preflight-go-2-ttl-1` code commit `5b3dcd83208386627c2793e8bf041c6bcb929e71`. Archive SHA-256: `672624e52c7a776a6c496e527f49b3acb18ecd6456c6b7a1e88119d78537192c`. `composer.lock` SHA-256: `a7a7a5941baca4d2b4e782894f5d8f5e2292a05b0f0374d291c1490a33991cc5`; runtime PHP 8.4.24, `firebase/php-jwt` v7.2.2, `devsys/shared` pinned and installed at `b845450b2d4004fa31a5e3a540184fff42c7ebba`.

The release was copied from the complete active Office release and overlaid only `.env.example` and `OfficeInvoiceRecipePreflightService.php`; tests remain in the source branch. `.env` and persistent cache are external symlinks and were not archived as data. The manifest, lock and archive checksums passed.

The Office vhost was backed up and changed with exactly four absolute `DocumentRoot`/`<Directory>` substitutions. Apache configtest passed, `current` moved to the candidate, and one graceful Apache reload completed. PHP-FPM was not reloaded or restarted. The fresh pre-switch vhost backup is `/home/devsys/office-deploy/backups/office-invoice-draft-preflight-ttl-20261123-5b3dcd8-pre-switch/`; its checksum passed. The previous Office release remains available for rollback.

Read-only probes passed: Office home 302, invoice JS and dashboard CSS 200 with unchanged hashes, `/Invoices/data` without session 401, and `GET /Invoices/recipePreflight` 405 (the action is POST-only; no POST was sent). API health, discovery, JWKS and login remained 200; invoice collection without bearer 401; API diagnostic 404 with `no-store`; login host diagnostic path 404. Services remained active and configuration checks passed.

Office diagnostic enable/expectation/lease keys were absent and `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`. API diagnostics and private expectations were absent, API invoice write/recette gates OFF, source allowlist absent/empty. No PHP-FPM overrides were observed. No diagnostic request, invoice write, SQL, migration, or systemd installation occurred. Live authenticated expiry remains `NOT_RUN`.

Tests before packaging: Office `composer test` PASS, 164 tests / 470 assertions; API suite PASS, 307 tests / 947 assertions, 16 SQL-dependent skipped. Composer validation, platform checks, audit, PHP lint, and diff check passed. `npm run build` was N/A; no assets changed.
