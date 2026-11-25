# OFFICE-INVOICE-FACTURX-2-ISSUE-POLICY-DEPLOY

**Verdict: `BLOCKED_DISK_SPACE_CRITICAL`** — the issue-policy code is merged, but the Office release was not built or activated.

The coordinated merges completed on 2026-11-25: shared PR #12 at `a3a17da2b840e7144376cb58eaca89407f3c39af`, Office-Api PR #26 at `e7461561d459b7b4bd381755a15537aee91c68f9`, and Office PR #19 at `8919628bb963acac5f02fde6d013c8e6908bc477`. Office locks `devsys/shared` to source commit `f1215a8f0a32c660115e64de75504018c930053e`.

The active Office release remains `office-invoice-facturx2-20261124-eb2f7aa`; the active API release remains `api-invoice-facturx2-20261124-7f88c7c-r1`. Only 80 MB was free on the shared release volume, while these active releases occupy about 153 MB (Office) and 462 MB (API). To preserve atomic deployment and rollback, deployment stopped before packaging or switching releases.

No Office/API flags, `.env` files, services, vhosts, database schema, invoices, snapshots, archives, or numbering sequences were changed. The Office flags remain Factur-X/read API/draft API enabled as previously observed. API `INVOICE_FACTURX_ENABLED` and invoice write gates also remain as observed. The scheduled backup service last reported success at 2026-11-25 03:26 CET.

No production smoke test was run. Deployment-lot tests were not rerun; prior merged-branch checks recorded Office 174 tests / 542 assertions and `npm run build` passing. The SDK lock commit is unchanged.

**Unblock:** restore adequate free space through the normal operations process, retaining headroom for full release extraction and rollback. Then resume the coordinated API-first deployment and verify migration 027 before activating Office. The prior Office release remains available for rollback.
