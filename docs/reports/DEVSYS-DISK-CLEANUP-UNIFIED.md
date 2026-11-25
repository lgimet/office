# DEVSYS-DISK-CLEANUP-UNIFIED

**Verdict: `PARTIAL`** — caches and six eligible Office worktrees were cleaned, recovering several GiB. A separate API worktree removal stopped on ownership permissions after Git had removed its worktree registration; its source files remain on disk and are present in `origin/main`. No source commit, release, backup, invoice, or business data was lost.

## Paths and storage

`/devsys` is absent. `/home/devsys` is a real directory on `/dev/sdb`, not a symlink. `/home/devsys/.cache` belongs to `devsys`; `/root/.cache` is on the separate `/` volume (`/dev/sda1`).

| Volume / directory | Before | After | Change |
| --- | --- | --- | --- |
| `/home/devsys` (`/dev/sdb`, 30G) | 79M free, 100% used | 11G free, 64% used | Approximately 10.9G more free |
| `/` (`/dev/sda1`, 50G) | Free-space value not captured before | 18G free, 63% used | Root Composer cache 6.0G → 4K; exact filesystem delta unavailable |
| `/home/devsys/.cache` | 7.6G | 864M | Composer cache 6.8G → 4K; Playwright 863M retained |
| `/root/.cache` | 6.2G | 201M | Composer cache 6.0G → 4K; Restic cache 201M retained |
| `/home/devsys/office-deploy` | 13G | 9.2G | About 3.8G less; worktrees 11G → 7.7G |
| `/home/devsys/api-deploy` | 993M | 993M | No material directory reduction; one generated `.pyc` removed |

The cache actions used `composer clear-cache` under root and `devsys`; no Composer/NPM process was running and `lsof` showed no open files in those cache directories. Inode use on `/home/devsys` fell from 13% to 11%. No `rm -rf`, Git prune, or cache-wide raw deletion was used.

## Worktree review and actions

PR states below were checked with GitHub. All listed working trees were checked for status and local/remote commit state; selected deletion candidates were additionally checked for process working directories and open files. No branches were deleted.

### Office

| Worktree | Result | Local state / GitHub relation | Approx. space |
| --- | --- | --- | ---: |
| `office-invoice-facturx-1` | Removed with `git worktree remove` (no force) | Clean; report commit remains on remote branch `work/office-invoice-facturx-1-deploy-report`; no open PR for that branch | 2.2G |
| `office-invoice-facturx-2-snapshot-diagnostic` | Removed | Clean; PR #18 merged; HEAD is in `origin/main` | 252M |
| `office-invoice-mvp-1` | Removed | Clean; PR #12 merged; HEAD is in `origin/main` | 320M |
| `office-repos-cleanup-1` | Removed | Clean; PR #8 merged; HEAD is in `origin/main` | 320M |
| `office-invoice-draft-preflight-go-2` | Removed | Clean; PR #4 closed; commit is in `origin/main` | 116M |
| `office-invoice-draft-preflight-go-2-ttl-1` | Removed | Clean; PR #5 closed; commit is in `origin/main` | 116M |

Office worktree removals reclaimed about 3.3G. Retained worktrees include the ISSUE-POLICY-DEPLOY path (`office-invoice-facturx-2-issue-policy`, 7.2G; explicitly protected), the Factur-X deploy report worktree (PR #16 open), the shared draft-preflight worktree (modified `.phpunit.result.cache` and no remote commit), local PHP 8.4 branches without remote commits, and the common checkout `office-invoice-web-deploy-1`. The unrelated 105M build directory `build-office-invoice-web-20261123-64b6b29-0661b92` is not a Git checkout and was retained. Small historical worktrees were retained to avoid a broader cleanup after sufficient space had been recovered.

Retained Office inventory, grouped by common state: `office-invoice-facturx-2-issue-policy` (7.2G, clean/pushed, explicitly protected); `office-invoice-facturx-2` (252M, clean, PR #16 open); `office-invoice-web-deploy-1` (127M, clean common checkout used by linked worktrees); local-only PHP 8.4 Office branches `office-deploy-php84-2-office`, `.1`, `.2` (13M + 5.2M + 5.2M, clean but their HEADs are not on a remote); `office-deploy-php84-2-shared` (15M, separate shared main checkout); `office-invoice-draft-preflight-1` (5.4M, clean, PR #3 closed, HEAD in main); `office-invoice-draft-recette-1` (5.4M, clean, HEAD in main, no matching PR); `office-invoice-draft-write-1` and `.1` (5.4M each, clean, HEADs in main, no matching PR); `office-invoice-mvp-3-facturx` (5.5M, clean, HEAD in main); `shared-invoice-draft-preflight-go-2` (14M, modified PHPUnit result cache, no remote commit). The 105M non-Git build directory is also retained. No release, artifact, or deployment backup directory was removed.

### Office-Api

No complete API worktree was removed. The old `office-invoice-facturx-1` report worktree (121M) was clean except for a generated Python `.pyc`; its commit is in `origin/main` and PR #22 is merged. I removed only that exact `.pyc`, then `git worktree remove` as `devsys` failed because numerous tracked source files are root-owned. Git had already removed the worktree registration, leaving the source directory on disk but no longer registered as a worktree. I did not force or manually delete it. The exact commit remains in `origin/main` and its remote branch; no local source changes were present. This residual directory is the reason for the `PARTIAL` verdict.

Other API worktrees were retained: the issue-policy deployment report (explicitly protected), deploy-result report (PR #25 open), issuer work (PR #17 open), MVP-1 deploy (PR #16 open), MVP-3 Factur-X (PR #18 open), and small historical/test/source worktrees. The API common checkout and the non-Git `office-api-invoice-draft-preflight-1` directory were preserved. No other worktree had uncommitted changes; the residual `.pyc` was the only API worktree change removed.

Retained API inventory: `office-api-guard-integration-1` (32M, clean, common checkout; PR #8 closed, HEAD in main); `office-api-invoice-draft-preflight-1` (20K, non-Git directory, retained); `office-api-release-tooling-check` (2.6M, detached clean checkout, HEAD in main); `office-invoice-draft-preflight-1` (2.7M, clean, PR #9 closed, HEAD in main); `office-invoice-draft-preflight-go-2` and `-ttl-1` (26M each, clean, PRs #10/#11 closed, HEADs in main); `office-invoice-draft-watchdog-1` (2.6M, clean, HEAD in main); `office-invoice-facturx-2-deploy` (121M, clean, PR #25 open); `office-invoice-facturx-2-issue-policy` (121M, clean/pushed, protected); `office-invoice-facturx-2-snapshot-diagnostic` (3.3M, clean, HEAD in main); `office-invoice-issuer-1` (2.9M, clean, PR #17 open); `office-invoice-mvp-1` (26M, clean, PR #16 open); `office-invoice-mvp-3-facturx` (2.9M, clean, PR #18 open); and `office-repos-cleanup-1` (26M, clean, PR #14 merged, HEAD in main). These remained because they are small, are open review work, or provide a common/explicitly protected checkout.

### shared

No shared worktrees were removed. The issue-policy report worktree was retained; the merge-report worktree has PR #10 open and a modified `.phpunit.result.cache`; the snapshot-diagnostic worktree has a modified `.phpunit.result.cache` despite merged PR #11; the Factur-X 1 report branch is pushed but not in `main`; and the common checkout is retained. These are small, and their local state or branch use did not justify further removal.

Shared inventory: `office-repos-cleanup-1` (common checkout, clean, HEAD in main); `office-invoice-mvp-3-facturx` (14M, clean, HEAD in main); `office-invoice-facturx-1` (14M, clean, report branch pushed but not in main); `office-invoice-facturx-2` (14M, modified PHPUnit result cache, PR #10 open); `office-invoice-facturx-2-issue-policy` (14M, clean/pushed, protected); `office-invoice-facturx-2-snapshot-diagnostic` (14M, modified PHPUnit result cache, PR #11 merged and HEAD in main). All were preserved.

## Releases and service checks

- Office `current` and both Office vhost DocumentRoots still point to `/home/devsys/office-deploy/releases/office-invoice-facturx2-20261124-eb2f7aa/public`.
- API `current` and both API vhost DocumentRoots still point to `/home/devsys/api-deploy/releases/api-invoice-facturx2-20261124-7f88c7c-r1/public`.
- The Office previous candidate `office-invoice-facturx2-20261124-c08b558` and API previous candidate `api-invoice-facturx2-20261124-9a7c00f` still exist. All release directories, manifests, artifacts and deployment backup directories were preserved; no release was deleted.
- Apache and PHP-FPM were active. Loopback-only checks after cleanup returned HTTP 200 for API `/api/v1/health` and HTTP 302 for the Office root. No service was restarted.
- Restic, its repository, timers, retention and backups were untouched. No backup or restore was run; the previously reported 25 November 03:26 CET backup was not revalidated here.

No configuration, source code, SQL, Factur-X archive, invoice, snapshot, legal number, or sequence was changed. No effective `git worktree prune` was run. A dry-run after the failed API worktree removal showed no stale administrative entries.

## Remaining safe next step

The API residual directory is about 121M and contains root-owned tracked files. Leave it in place unless an operator explicitly chooses a root-owned cleanup after confirming the source commit remains recoverable from GitHub. Do not run a broad prune or delete other worktrees/releases merely to empty the deployment volume. Current `/home/devsys` free space is about 11G.
