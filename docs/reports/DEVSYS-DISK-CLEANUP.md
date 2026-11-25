# DEVSYS-DISK-CLEANUP

**Verdict: `CLEANED`** — Composer download/source caches were cleared after confirming no Composer/NPM process was running and `lsof` showed no open files in the cache paths. No release, worktree, artifact, backup, application, or business data was removed.

## Confirmed paths and disk space

`/devsys` does not exist on this host. The deployment directories are under `/home/devsys`; that path is a real directory on `/dev/sdb`, not a symlink. `/home/devsys/.cache` belongs to `devsys`; `/root/.cache` belongs to root and is on `/dev/sda1`.

| Volume / path | Before | After | Result |
| --- | --- | --- | --- |
| `/home/devsys` (`/dev/sdb`, 30G) | 79M free, 100% used | 6.9G free, 76% used | ~6.8G freed on this volume |
| `/` (`/dev/sda1`, 50G) | Free-space reading not captured before cleanup | 18G free, 63% used | Root Composer cache reduced from 6.0G to 4K; exact volume delta unavailable |
| `/home/devsys/.cache` | 7.6G | 864M | Composer cache 6.8G → 4K; Playwright cache retained (863M) |
| `/root/.cache` | 6.2G | 201M | Composer cache 6.0G → 4K; Restic cache retained (201M) |

Inode use on `/home/devsys` changed from 13% (247,643 used) to 13% (245,716 used). The initial root-volume inode/free-space reading was not recorded.

## Preserved deployment state

- Office `current` still targets `office-invoice-facturx2-20261124-eb2f7aa`; API `current` still targets `api-invoice-facturx2-20261124-7f88c7c-r1`. Both targets exist.
- All releases, artifacts, deployment backups, and worktrees were retained. Current directory sizes: Office deploy 13G (worktrees 11G, releases 1.2G, artifacts 331M, backups 152K); API deploy 993M (worktrees 513M, releases 477M, artifacts 3.4M, backups 332K).
- No Composer/NPM process or release-switch helper was found; the release-switch lock file was not held. No process had a working directory in the Office deployment worktrees. Node services were left untouched.
- Apache and PHP-FPM were active. Loopback-only checks returned HTTP 200 for API `/api/v1/health` and HTTP 302 for the Office root. No service was restarted.
- No Restic command, backup, or restore was run. Backup health is not asserted by this cleanup.

## Cleanup and exclusions

Ran `composer clear-cache` as root and as `devsys`. No raw recursive deletion was used. The Playwright browser cache, the 13G Office deployment tree (including its 11G of Git worktrees), API deployment files, releases, artifacts, backups, temporary directories, and Restic data remain untouched. Large worktrees and older releases were not removed because their continued value and rollback use could not be ruled out safely.

No application code, configuration, SQL, invoices, Factur-X archives, or numbering data changed. No new cleanup tool was installed.
