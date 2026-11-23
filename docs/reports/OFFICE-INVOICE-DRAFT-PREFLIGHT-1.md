# OFFICE-INVOICE-DRAFT-PREFLIGHT-1 — Office

Date: 2026-11-23 (Europe/Paris)

**Decision: `BLOCKED_SERVER_PROOF_AND_WATCHDOG_RUNTIME`**

This is preparation only. All invoice write controls remain OFF. No invoice
form was submitted, no SQL or API mutation was sent, and no production
configuration, permission, route, service, timer, or release was changed.

## References and operator declaration

- Office `main`: `0f9d6e98ad21e0b19c89888fed23d5a7b2d7dc9e`.
- Office write-preparation source: `648930e4a2a391462112f922a5dc752558e33c2e`,
  parent `76549a3b6726f8eb9a974bed9c5821b9993348b4`.
- API `main`: `c976b6a8e3722aca63fcbe3c73e6e6ea836584cf`; guard integration:
  `a07573566f2f08986afe70f722c714a0615a046c`; watchdog source:
  `5efd44be4c7f94e9f7e8356aa94527be989f1985`; preflight/watchdog hardening:
  `ea92fb955226ee36702781f07a5e9202508ae137`.
- shared `main`: `83e5b7da32ae20039218586e18dc93ee9ad95852`; Office SDK lock:
  `0661b92b914c28c41f45f5bd19bb9a860c232676`.
- Reported active Office release:
  `office-invoice-web-20261123-64b6b29-0661b92`; API/login release:
  `api-invoice-draft-guard-20261123-aa9be47-r1`.

The operator declared PASS for authenticated OIDC login, dashboard, invoice
list, client search, and existence of an authorized fictional client, and
declared that no business write occurred. This is an operator declaration;
Codex did not receive or use the session, bearer, cookie, or client identifier.

## Identity and Office flow

Static review confirms the invoice service uses the OIDC session-backed
`OfficeAccessTokenProvider` through `shared/InvoicesApi`; it does not use the
separate client-credentials token provider. `TenantContext` resolves tenant
membership server-side. The API then resolves its own principal, effective
tenant, and `devsys` source through invoice access middleware.

No safe existing path proved the attributes of the actual API request or that
the session bearer is admitted for `write:invoices`. Therefore subject,
tenant, source and write-scope alignment are **NOT_RUN**. No IDs or claims are
recorded. A temporary API GET returning only match booleans is proposed in the
API report, but it is not implemented or deployed and requires a distinct
diagnostic GO. No scope or authentication change is proposed.

The earlier report's key safety point remains: with
`OFFICE_INVOICE_DRAFT_WRITE_API_ENABLED=false`, `saveDraft()` can select the
legacy SQL path. No form submission or “expected refusal” probe was made.
Any later recipe must prove the API path is selected and fail-closed on API
errors, without a legacy SQL fallback.

## Watchdog and permission state

The API watchdog preflight is prepared on the dedicated branch
`work/office-invoice-draft-preflight-1`. Its 18 sandbox tests exercise
persistent state, expiry bounds, partial failure and simulated boot-recovery
using temporary files and fake systemd inputs. It is not installed, armed, or
proven against a real systemd timer, reboot, or PHP-FPM request. The API `.env`
is world-readable (`0644`); GO 1 is required before the proposed `0640`
permission change.

The proposed boot recovery introduces an Apache-wide startup dependency and
has only journal logging, not a verified external paging channel. The actual
runtime effect of closing dotenv flags remains untested; no FPM reload is
authorized. See the API report for the exact file permissions proposal,
watchdog template paths, tests, and installation boundary.

## GO boundaries and remaining requirements

1. GO 1 is required before API `.env` metadata/permission change to `0640`.
2. GO 2 is required before deploying and calling the temporary identity/scope
   diagnostic through the operator's legitimate session.
3. GO 3 is required before installing the watchdog or its systemd units. It
   must accept the Apache-wide startup dependency and authorize OFF-only
   systemd/FPM validation.
4. A separate mutation GO remains required later. It must approve one create
   and up to three PATCH operations on one existing fictional-client draft,
   one server-validated user/tenant, and a maximum 900-second window. No such
   approval is inferred here.

`write:invoices` proof, private allowlist match, timer expiry under real
systemd, reboot recovery, and effective OFF in PHP-FPM are `NOT_RUN`. No
mutation was performed. Migration 024 was not re-run; earlier metadata
verification is historical evidence only.

The API watchdog test results and exact SHA are recorded in
`Office-Api/docs/reports/OFFICE-INVOICE-DRAFT-PREFLIGHT-1.md` on branch
`work/office-invoice-draft-preflight-1`.
