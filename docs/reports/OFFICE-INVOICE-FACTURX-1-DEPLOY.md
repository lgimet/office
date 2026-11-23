# OFFICE-INVOICE-FACTURX-1-DEPLOY — Office

Date: 2026-11-23 (Europe/Paris)
Status: **BLOCKED_PRECHECK**

## Source and deployment state

- Office merge commit on `main`: `7e962eaabd80ea5b9dbdbe4b43f43a4206e9217b`; candidate feature tree matches the merged tree.
- Current Office release: `office-invoice-mvp2-20261123-86df387`; `/home/devsys/office-deploy/current` remains on that release. Its release manifest checksum validation passed.
- Existing Office archive SHA-256: `04e4a5d0d2b8a56d99ef9f760975a8cd500f770407009211b852ec4bdc705125`; archive sidecar validation passed.
- Current Office write-through-API flag is ON; no environment value was changed.
- Current Office lock still references shared SDK `b845450b2d4004fa31a5e3a540184fff42c7ebba`. The merged candidate lock and isolated vendor resolve to `d2b52a83a7265b4fa158090549462ec72c26ad45`. The candidate SDK was not installed in production.

## Validation performed

- Office `composer test`: PASS — 171 tests, 519 assertions.
- `composer validate --no-check-publish`: valid; warning that no license is specified.
- `composer check-platform-reqs --no-dev`: PASS.
- `composer audit --locked --no-dev`: PASS, no advisories.
- `npm run build`: PASS. PHP lint on 3 changed PHP files and `git diff --check`: PASS.
- Read-only Office root probe returned 302, consistent with its login redirect.

Office was not deployed because API migration 025 and its compatible API release could not be installed under the current release preflight, which refuses the active API write-gate state. No invoice form was submitted; no invoice or client was created or changed. No Apache reload or PHP-FPM restart occurred.

Next action: resolve the API release-tooling / active-gate precondition, then deploy API (including migration 025) before deploying this Office release.
