# OFFICE-INVOICE-DRAFT-PREFLIGHT-GO-2-DEPLOY — Office

Date: 2026-11-23, Europe/Paris
Status: **DEPLOYED_DIAGNOSTIC_OFF** — awaiting a separate diagnostic activation GO. This is not approval for invoice writes.

## Source and release

- Office source branch/HEAD: `work/office-invoice-draft-preflight-go-2` / `7badc6cdbe50198b08e0f3c089788ca771336714`.
- Runtime overlay commit: `06aab44ae40723b0fcad4951cdbb97e5d2d527ba`.
- Complete runtime base: `office-invoice-web-20261123-64b6b29-0661b92`.
- Deployed release: `office-invoice-draft-preflight-20261123-7badc6c` at `/home/devsys/office-deploy/releases/office-invoice-draft-preflight-20261123-7badc6c`.
- Durable archive: `/home/devsys/office-deploy/artifacts/office-invoice-draft-preflight-20261123-7badc6c.tar.gz`; SHA-256 `10434241c4c5346c87c8c616a5b01a023140324e33518dfb875f84b3176ce99c`.
- `composer.lock` SHA-256: `a7a7a5941baca4d2b4e782894f5d8f5e2292a05b0f0374d291c1490a33991cc5`; PHP 8.4.24; `public/index.php` SHA-256 `a77c5875b2f12c03eb23e0187dd1e7b105acfed420ad9aa35511abf27c0a9f81`.
- SDK `devsys/shared` was installed from the locked source reference `b845450b2d4004fa31a5e3a540184fff42c7ebba` (branch head `461af1a0f5b1f7048d7c1d8b23e2e526498e0dd4`); installed package is a real directory, not a mutable symlink.
- Candidate and archive checksum checks passed. `.env` and persistent cache are archived as external symlinks only.
- Active `current` and the four HTTP/HTTPS DocumentRoot/Directory values in `/etc/apache2/sites-enabled/office.devsys.fr.conf` now resolve to the immutable release path above. Previous release retained: `office-invoice-web-20261123-64b6b29-0661b92`.

## Change and operation

The release was assembled from the complete active Office release, applying only the GO-2 files listed in its release manifest. No frontend source changed. The Office action is POST-only, protected by the existing authenticated/CSRF path and an OFF-by-default operator flag; no browser bearer, tenant, subject or client value is accepted as authority. The action delegates through the Office service and the pinned SDK.

After API/login was switched and its anonymous GET probes passed, at about 08:37 CET the Office vhost was backed up, rendered with exactly four DocumentRoot/Directory path substitutions, atomically replaced, validated with `apachectl configtest`, and Apache was gracefully reloaded once. The `current` marker was atomically updated. PHP-FPM was not reloaded or restarted.

- Vhost backup: `/home/devsys/office-deploy/backups/office-invoice-draft-preflight-20261123-7badc6c-pre-switch/office.devsys.fr.conf.before`.
- Backup copy checksum manifest: `BACKUP-COPY-SHA256SUMS`; original state checksum is also recorded in `SHA256SUMS`.
- Rendered vhost and checksum: `/home/devsys/office-deploy/artifacts/vhost-office-invoice-draft-preflight-20261123-7badc6c/`.
- Rollback: atomically point `current` back to the retained previous release, restore the saved vhost bytes, run `apachectl configtest`, then gracefully reload Apache. No rollback was needed.

## Validation

- `composer test` on source worktree at recorded HEAD: PASS, 143 tests, 397 assertions.
- Candidate `composer validate --no-check-publish`: PASS with the existing missing-license warning.
- `composer check-platform-reqs --no-dev`: PASS on PHP 8.4.24.
- `composer audit --locked --no-dev`: PASS, no advisories.
- PHP lint for the added controller and service files: PASS.
- Shared SDK `composer test` at its checked-out commit: PASS, 50 tests, 211 assertions.
- API `composer test`: PASS, 285 tests, 916 assertions, 16 skipped (database-dependent).
- API and Office release checksums: PASS. API switch dry-run and Office exact-path render comparison: PASS. Apache `configtest` and `php-fpm8.4 -t`: PASS.
- `npm run build`: N/A; no frontend code/assets were changed. Assets from the complete active release were retained.
- No SQL or MySQL integration test was run. Migration 024 was not rerun.

## Read-only production checks

| Probe | Result |
|---|---:|
| Office home | 302 (existing login redirect) |
| Invoice data route without session | 401 |
| invoice JavaScript | 200 |
| dashboard CSS | 200 |
| GET to diagnostic action path | 405 (action is POST-only; no POST was sent) |
| API health | 200 |
| login discovery / JWKS / login page | 200 / 200 / 200 |
| API invoices without bearer | 401 |
| API diagnostic path while OFF | 404, `no-store` |
| login host at API diagnostic path | 404 |

No authenticated Office session was inspected or used. The GET 405 does not exercise the diagnostic action; its availability is established by code and the flag remains OFF. No invoice/client mutation, diagnostic invocation, SQL fallback, migration or authenticated request occurred.

## Effective flags and limits

After switching, the active Office dotenv had `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`; `OFFICE_INVOICE_PREFLIGHT_ENABLED` was absent. The active API dotenv and visible FPM environments had `INVOICE_DRAFT_PREFLIGHT_ENABLED`, `INVOICE_DRAFT_RECETTE_GUARD_ENABLED`, and `INVOICE_WRITES_ENABLED` absent/OFF and `INVOICE_WRITE_SOURCES` absent/empty. No named process overrides were found. No flags or environment values were changed.

`stationid.devsys.fr` returned 301. `spotcard.devsys.fr` and `menuflow.devsys.fr` did not resolve from this host and have no local Apache vhost here; those witness checks are NOT_RUN, not passed. No authenticated OIDC flow was tested in this deployment lot.

## Final state and next authorization

API/login and Office serve their new versioned releases; Apache and PHP-FPM remain active; Apache config test passes. Two graceful Apache reloads were performed, one after API/login and one after Office. The diagnostic and every invoice write gate remain OFF. No session identity/scope proof was obtained. Next step is a distinct diagnostic activation GO, with private expected identity/tenant configured only under that authorization. Any invoice create/PATCH requires a separate mutation GO; issue/delete remain excluded.
