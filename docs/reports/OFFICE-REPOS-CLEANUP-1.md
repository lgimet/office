# OFFICE-REPOS-CLEANUP-1 — Office DevSys

## Scope and source refs

This is a Git consolidation only. Integration branch `work/office-repos-cleanup-1` started from `office/main` at `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`, then incorporated the functional PHP 8.4 deployment lineage and the Office invoice-draft/preflight/TTL work. Candidate HEAD before this report: `dc8f42fe5db62eec1b0422fceb0d531da0dbec0a`. API and SDK consolidation candidates are documented in their respective reports.

The active releases observed before this cleanup were API/login `api-invoice-draft-preflight-ttl-20261123-1623128` and Office `office-invoice-draft-preflight-ttl-20261123-5b3dcd8`. This task did not change release pointers, vhosts, services, database, or flags. The required TTL-DEPLOY reports were added to the consolidation branches from the contemporaneous operation records; authenticated expiry behavior remains NOT_RUN because diagnostics stayed OFF.

The Office lock resolves `devsys/shared` to `b845450b2d4004fa31a5e3a540184fff42c7ebba`. A clean Composer install using `--prefer-source` installed that exact commit; dist download returned GitHub 404, so source mode was used without changing the lock or running `composer update`.

## Tests

- `composer validate --no-check-publish`: PASS (existing missing-license warning).
- `composer check-platform-reqs --no-dev`: PASS on PHP 8.4.24.
- `composer test`: PASS, 164 tests / 470 assertions.
- `npm ci` and `npm run build`: PASS; npm reported no vulnerabilities. `node --check public/assets/js/Object/Invoices.js`: PASS.
- PHP lint on all PHP files changed relative to `main`: PASS. `git diff --check`: PASS.
- `composer check-platform-reqs --no-dev`: PASS on PHP 8.4.24; `composer audit --locked --no-dev`: PASS, no advisories.
- No production write, SQL, migration, release deployment, or flag change was performed.

## Branches, PRs, and releases

Before this consolidation, GitHub showed 15 Office branches and 4 open PRs. The open PRs were #2 (drafts, based on PHP 8.4 base), #3 (read-only preflight), #4 (operator diagnostic), and #5 (TTL). The consolidation candidate contains the base and all feature work on that path. Final PR state, merges, branch deletions, counts, main SHA, and retained branches are recorded in the completion update below.

### Completion update — verified 2026-11-23

| Repository | Branches before → after | Open PRs before → after | Final `main` | Consolidation PR |
|---|---:|---:|---|---|
| `office` | 15 → 2 | 4 → 0 | `93fa76bad253091490c02e4b13823f9ee6286eb4` | [#6 merged](https://github.com/lgimet/office/pull/6) |
| `Office-Api` | 21 → 7 | 9 → 0 | `33398b1245e610d1f53cb38020a59398bafbbba2` | [#12 merged](https://github.com/lgimet/Office-Api/pull/12) |
| `shared` | 7 → 1 | 2 → 0 | `8eb7d2eff1ca9688bf0acc020f580bb08a98a79b` | [#5 merged](https://github.com/lgimet/shared/pull/5) |

The consolidations were merged in dependency order: shared, API, Office. GitHub marked the existing API #3 and shared #3 merged because their commits were included. Other draft PRs were closed with a reference to the relevant consolidation PR.

Branches deleted were checked for ancestry in `main`; the Office UI branch with a distinct tree and API branches carrying unique reports/tools were retained. API retains `feature/office-api-inv-recette-guard-1`, `work/office-api-deploy-guard-1`, `work/office-api-db-validation-1`, and `work/office-api-reconcile-{1,2,3}`. Office retains `feature/office-ui-inv-1`. Shared has only `main`.

Read-only post-cleanup check: API `current` still resolves to `api-invoice-draft-preflight-ttl-20261123-1623128`; Office `current` still resolves to `office-invoice-draft-preflight-ttl-20261123-5b3dcd8`. No deploy, vhost change, DB operation, or flag change occurred.

Decision: `CLEANUP_COMPLETED` for the safe consolidations and deletions; seven secondary branches remain because their contents are not wholly represented in `main`.
