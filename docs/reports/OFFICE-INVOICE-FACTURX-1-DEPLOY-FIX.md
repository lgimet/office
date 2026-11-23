# OFFICE-INVOICE-FACTURX-1-DEPLOY-FIX — Office

Date: 2026-11-23 (Europe/Paris)
Status: **FACTURX_1_DEPLOYED_READY_FOR_USER_DRAFT**

## Source and release

- Office `main`: `7e962eaabd80ea5b9dbdbe4b43f43a4206e9217b`; PR #13 is merged.
- Active release: `office-invoice-facturx1-20261123-7e962ea`; archive SHA-256 `63205f00ee1c8758830ffa54b145f08075e99096307c99533afbf841dc248951`.
- `/home/devsys/office-deploy/current` and both DocumentRoot/Directory pairs in the enabled Office vhost target its absolute `public` directory. Previous release `office-invoice-mvp2-20261123-86df387` remains intact.
- `composer.lock` SHA-256: `b6869bf63474816c82894eb0ac29642e204885f81beaf9c295424200c51f2150`. The runtime vendor metadata confirms `devsys/shared` at the exact locked SHA `d2b52a83a7265b4fa158090549462ec72c26ad45`. Composer dist was unavailable for this private commit; installation from the exact locked source succeeded, with no floating update.
- External `.env` and `var/cache` links are preserved. `OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=true` remained unchanged.

The compatible API release and additive migration 025 were active and verified before Office was switched.

## Switch and read-only checks

- The enabled Office vhost was backed up at `/home/devsys/office-deploy/backups/office-invoice-facturx1-20261123T2201+0100-pre-switch`. The rendered vhost changed only its four absolute DocumentRoot/Directory paths.
- Apache configtest passed; one graceful Apache reload followed. PHP-FPM was not reloaded or restarted.
- Post-switch GET results: Office root 302; invoice data without session 401; invoice form CSS 200; API health 200; anonymous invoice list 401; OIDC discovery/JWKS/login 200. Apache and PHP-FPM are active.
- Release and archive checksums passed. No invoice form was submitted and no API business mutation was called by Codex.

## Validation

- `composer test`: PASS — 171 tests, 519 assertions.
- `composer validate --no-check-publish`: valid, existing missing-license warning.
- `composer check-platform-reqs --no-dev`: PASS; `composer audit --locked --no-dev`: PASS, no advisories.
- `npm run build`: PASS; PHP lint on 3 changed PHP files and JavaScript syntax check: PASS; `git diff --check`: PASS.

Office’s API draft mode stayed ON throughout, so an attempted save during the temporary API gate closure would have failed through the API path rather than falling back to legacy SQL. No client or invoice was created, modified, issued, deleted, numbered, or exported. The user may now create a complete new draft; this release does not generate Factur-X documents.

Rollback: restore the saved Office vhost bytes, point `current` to `office-invoice-mvp2-20261123-86df387`, run `apachectl configtest`, then gracefully reload Apache. Keep migration 025 and its nullable columns in place.
