# OFFICE-INVOICE-WEB-DEPLOY-1

**Date:** 2026-11-23 (Europe/Paris)  
**Decision:** `DEPLOYED`
**Deployment state:** deployed read-only on 2026-11-23; authenticated invoice workflow was not tested because no operator session was available.

## Source and release identity

| Component | Reference used | Result |
|---|---|---|
| Office `main` | `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e` | Ancestor of the functional branch; not selected as the build source. |
| Office base `work/office-deploy-php84-2.2` | `1333e34aac9f1738afaf795efdcb77044be163e9` | Functional base preserved. |
| Office source `feature/office-inv-recette-1.2` | `64b6b298c6dc049c06b671e02e813b93255da090` | Build source and current head of dedicated report branch `work/office-invoice-web-deploy-1`. |
| Shared SDK locked by Office | `0661b92b914c28c41f45f5bd19bb9a860c232676` | Exact `devsys/shared` source reference in `composer.lock`; installed package is a real directory, not a symlink. |
| Shared feature head checked | `6b99578cdc604d4363091d1cbe7d2115be1d88f9` | Current reference checked during preparation; Office does not resolve a floating branch. |
| Office PR #2 | Draft, open, mergeable; targets `work/office-deploy-php84-2.2` | No merge performed. |
| API release | `api-invoice-compat-20261123-ce80025-r2` | Deployment recorded in `Office-Api/docs/reports/OFFICE-API-RELEASE-SWITCH-1.md`; read-only health and OIDC discovery checks passed there. Authenticated invoice GET was not demonstrated. |

The Office source adds the invoice draft API flow to the 2.2 base. The scoped source diff is 11 files (`+3916/-29`): Composer manifest/lock, invoice controller/service, Twig form, invoice JavaScript, two targeted unit tests, and related reports/ignore rules. No API, shared, OAuth, database, or production source was changed by this task.

## Candidate artifact

- Release ID: `office-invoice-web-20261123-64b6b29-0661b92`
- Archive: `/home/devsys/office-deploy/artifacts/office-invoice-web-20261123-64b6b29-0661b92.tar.gz`
- Archive SHA-256: `121801a14bec6f912807bbeec7e08cf399ca2a041301e4ff1786afbd9b4d6851`
- Archive size: 47,847,401 bytes
- Inactive extracted release: `/home/devsys/office-deploy/releases/office-invoice-web-20261123-64b6b29-0661b92`
- `composer.lock` SHA-256: `ce2ba7dea44f6a18b5b9d83eddb6b5e490f13aa8c959d9dc8e2dadd415a40676`
- PHP used: 8.4.24; locked `firebase/php-jwt`: 7.2.2.
- Manifest and `SHA256SUMS` are included; checksum verification after extraction passed for 1,528 files.
- The archive excludes the actual `.env`, Git metadata, `node_modules`, tests, sessions, uploads, storage and cache data. The inactive release's `.env` and cache paths resolve to the existing external paths; storage remains at the configured external `OFFICE_STORAGE_DIR`.
- `vendor/devsys/shared` is a directory, not a symlink. Composer installed metadata confirms source reference `0661b92b914c28c41f45f5bd19bb9a860c232676`. The SDK exposes the expected `business_reference` DTO behavior.
- Archive and release are owned `devsys:www-data`; archive mode `0640`, release root `0750`, and `public/index.php` `0640`. Read/traversal checks for `www-data`, autoload, external env, and cache access passed.

The first `composer install --no-interaction --prefer-dist` attempt failed because GitHub returned 404 for the locked private SDK archive and Composer's configured source fallback was disabled for that option. Retrying with `composer install --no-interaction` succeeded from the lock using VCS source. The production-style `composer install --no-dev --no-interaction` also completed in the isolated staging area. No `composer update` was run. This builder therefore needs authorized Git read access to the locked Shared commit.

## Checks run on isolated source/staging

| Check | Result |
|---|---|
| `composer validate --no-check-publish` | PASS; existing missing-license warning only. |
| `composer check-platform-reqs` | PASS on PHP 8.4.24. |
| `composer test` | PASS: 138 tests, 384 assertions. |
| `composer audit --locked --no-dev` | PASS: no production advisories. |
| PHP lint on changed PHP files | PASS: controller, draft service, SDK adapter and two touched unit test files. |
| `node --check public/assets/js/Object/Invoices.js` | PASS. |
| `npm ci` | PASS: 17 packages; zero vulnerabilities reported. |
| `npm run build` | PASS. |
| `git diff --check` | PASS on source checkout. |
| Release archive checksums and exclusion scan | PASS. |
| MySQL integration tests | NOT_RUN; no database test execution was needed or authorized for this read-only web release preparation. |
| Authenticated OIDC and invoice list/detail | NOT_RUN; no operator session was supplied. |

The dashboard CSS checksum matches the currently served file. The invoice JavaScript checksum differs as expected because the candidate includes the draft workflow. No assets in the active site were written.

## Active Office baseline and safety state

- Vhost: `/etc/apache2/sites-enabled/office.devsys.fr.conf`; `DocumentRoot` and `<Directory>` currently use `/home/devsys/office-deploy/current/public`.
- Active `current` resolves to `/home/devsys/office-deploy/releases/office-php84-20261122-7f84691-83e5b7d`.
- Current vhost SHA-256: `7e0fe3fee37e0012c16896bcbad7d7deebbbed595aa2755c4437fff4ed311929`.
- A fresh byte-for-byte preflight copy exists at `/home/devsys/office-deploy/backups/office-invoice-web-20261123-64b6b29-0661b92-preflight/office.devsys.fr.conf.before`; its manifest records matching checksum. This is a vhost configuration backup, not a database restore test.
- Apache config test: PASS (`Syntax OK`). `php-fpm8.4 -t`: PASS. Apache and PHP-FPM 8.4 were active; no reload/restart was performed.
- PHP-FPM is 8.4.24. `realpath_cache_ttl=120`, OPcache enabled, `validate_timestamps=On`, `revalidate_freq=2`. A symlink-only switch is not treated as immediate proof of the served release.
- Office flags observed without printing other environment values: `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`, `OFFICE_INVOICE_READ_API_ENABLED=true`.
- API invoice write flag/source allowlist/recipe guard are absent from the external API environment; the deployed API defaults are OFF/empty, consistent with its deployment report. No flag was changed.
- Migration 024 is recorded as verified on the active API schema in `OFFICE-API-RELEASE-SWITCH-1.md`; this task did not access SQL or run the migration.

### Read-only HTTP baseline (loopback GET, no cookies or bearer tokens)

| Request | Result |
|---|---|
| Office `/` | 302 |
| Office dashboard CSS | 200; checksum matches candidate. |
| Office invoice JavaScript | 200; candidate differs because of draft UI changes. |
| Office `/Invoices/data` without session | 401 |
| API `/api/v1/health` | 200 |
| API `/api/v1/invoices` without bearer | 401 |
| Login OIDC discovery | 200 |
| SpotCard witness root | 404 baseline |
| MenuFlow witness root | 200 baseline |

These anonymous probes do not demonstrate a successful authenticated Office/OIDC session or invoice read. No authenticated session was available, so those checks remain `NOT_RUN`.

## Planned activation and rollback (before the GO)

The vhost currently uses a symlink path, while PHP-FPM's realpath cache has a 120-second TTL. For a future separately approved change, use the absolute candidate path in the Office vhost to make the requested code target explicit:

```text
old: /home/devsys/office-deploy/current/public
new: /home/devsys/office-deploy/releases/office-invoice-web-20261123-64b6b29-0661b92/public
```

The dry-run found exactly four path substitutions in the Office vhost (the HTTP/HTTPS `DocumentRoot` and matching `<Directory>` paths); reversing them restores the original bytes. No TLS, auth, proxy, PHP handler or other-host directive changes are proposed. After backup and preflight, a future GO would authorize the four substitutions, `apachectl configtest`, and one graceful Apache reload only if the vhost changed. Then update `current` to the candidate as bookkeeping. Do not reload PHP-FPM.

Rollback: atomically point `current` back to `office-php84-20261122-7f84691-83e5b7d`, restore the fresh vhost backup byte-for-byte, run `apachectl configtest`, and gracefully reload Apache if the vhost changed. Recheck Office and witness GET baselines. Do not touch the database or delete either release.

No switch dry-run script is installed for Office; the non-mutating dry-run was an exact-path replacement assertion against the current vhost bytes. During that preparation stage, no active config write, symlink change, service reload, HTTP mutation, SQL or invoice operation occurred. The separately authorized activation is recorded below.

## Production activation and read-only checks

The operator gave a distinct GO for this exact Office release and SHA-256. The final preflight immediately before change confirmed the archive hash, the existing release tree, `www-data` readability, the old `current` target, the expected vhost hash, Apache/FPM configuration tests, and flags. A fresh vhost backup was created at:

`/home/devsys/office-deploy/backups/office-invoice-web-20261123-64b6b29-0661b92-deploy/office.devsys.fr.conf.before`

Its SHA-256 is the same as the pre-change active vhost: `7e0fe3fee37e0012c16896bcbad7d7deebbbed595aa2755c4437fff4ed311929`.

At approximately 04:44 Europe/Paris, only the four `DocumentRoot`/`<Directory>` path values in `/etc/apache2/sites-enabled/office.devsys.fr.conf` were replaced with the absolute candidate path. The exact inverse replacement restores the pre-change file. `apachectl configtest` returned `Syntax OK`; one `systemctl reload apache2.service` completed. PHP-FPM was not reloaded or restarted. The `current` symlink was then atomically changed to the candidate release.

Post-switch state:

- `current` resolves to `/home/devsys/office-deploy/releases/office-invoice-web-20261123-64b6b29-0661b92`.
- The four Office vhost path directives use `/home/devsys/office-deploy/releases/office-invoice-web-20261123-64b6b29-0661b92/public`.
- Active vhost SHA-256: `7e43eb0b0596f09f0a5d3fb2b029189193c4ca419377fc67c85f68fb07305c59`.
- Apache and PHP-FPM 8.4 remain active; active release entrypoint and Composer autoloader remain readable by `www-data`.
- The archive SHA remains `121801a14bec6f912807bbeec7e08cf399ca2a041301e4ff1786afbd9b4d6851`.

Read-only GET checks at 04:45–04:47 Europe/Paris:

| Request | Result |
|---|---|
| Office `/` | 302 to the existing login flow. |
| Office `/auth/oidc/login?return_to=%2Fdashboard` | 302; no interactive login was completed. |
| Office dashboard CSS `/assets/css/dashboard.css` | 200; SHA-256 `18b518a05919af7fd981a2ed0459318b9e409e790634776eaec86ca953ae3b9f`, matching candidate. |
| Office login CSS `/assets/css/login.css` | 200; SHA-256 `b119e224cb980478c598a8b8176f5e4db94d58f679313d2a36bff07ef515ef09`, matching candidate. |
| Office invoice JS `/assets/js/Object/Invoices.js` | 200; Office prepends its existing dynamic `DEBUG` comment. After that three-line comment, the response matches the candidate file byte-for-byte; candidate payload SHA-256 `6a189e2253b3ca7f0d61dbbf541789be133235ff9862ed1392e971a5b3e94c52`. |
| Office `/Invoices/data` without session | 401, expected authentication protection. |
| Office `/Invoices/list` with GET | 405, expected route method protection; no mutation was attempted. |
| API `/api/v1/health` | 200. |
| API `/api/v1/invoices` without bearer | 401, expected authentication protection. |
| Login OIDC discovery | 200. |
| SpotCard witness root | 404, matching pre-switch baseline. |
| MenuFlow witness root | 200, matching pre-switch baseline. |

The recent Office error log had no PHP fatal entries or unexpected 5xx evidence. FCGI error-log records at probe time correspond to the expected anonymous authentication refusal and GET method refusal; their source paths resolve to the candidate release. No authenticated OIDC session or invoice list/detail GET was available, so those are `NOT_RUN`; the successful anonymous checks do not claim authenticated functionality was exercised. API health and unauthenticated access control were verified, but an authenticated Office-to-API invoice read was not.

Flags after deployment, inspected without printing unrelated environment values:

- `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`.
- `OFFICE_INVOICE_READ_API_ENABLED=true` (preserved).
- API `INVOICE_WRITES_ENABLED` and `INVOICE_WRITE_SOURCES` are absent from the external environment; the active API code defaults the write gate to false and the allowlist to empty. The recipe guard setting is absent and defaults OFF. No flag changed.

No invoice/client data was created or changed. No SQL, migration, scope, OAuth configuration, API/login vhost, other application, or PHP-FPM service was modified. No rollback condition was met; deployment remains active.

### Rollback if a later issue is found

The former release is retained at `/home/devsys/office-deploy/releases/office-php84-20261122-7f84691-83e5b7d`. Restore `current` atomically to that target, restore the fresh vhost backup above byte-for-byte, run `apachectl configtest`, and gracefully reload Apache if the vhost file changed. Then recheck Office, API health, OIDC discovery, and witness GET baselines. Do not restore or modify the database and do not remove either release.

## Operator approval still required

To proceed, the operator must explicitly approve `office.devsys.fr`, release `office-invoice-web-20261123-64b6b29-0661b92`, archive SHA-256 `121801a14bec6f912807bbeec7e08cf399ca2a041301e4ff1786afbd9b4d6851`, the four exact vhost path substitutions, the window, one graceful Apache reload if needed, and the rollback above. This report and any prior approval for API/login do not authorize the Office switch.

After a separate GO, verify the candidate is actually served, check homepage/OIDC redirects, CSS/JS, protected routes, logs, and flags. Authenticated invoice list/detail remain contingent on an operator-provided legitimate session. Keep draft writes OFF; do not issue or delete invoices.

A later draft-writing recipe is a separate lot requiring an authorized operator session, tenant isolation, API guard and specific GO limited to create/PATCH. Emission, deletion, payments and external integrations remain excluded.
