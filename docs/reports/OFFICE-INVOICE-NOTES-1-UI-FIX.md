# OFFICE-INVOICE-NOTES-1-UI-FIX

Date: 2026-11-25  
Status: `FIX_READY_FOR_REVIEW`

## Diagnostic

Audited `office/main` at `7df0921245494c97297c198695fc373af2e762ae`. The active Office pointer resolves to `office-invoice-notes1-20261125-7df0921-r1`; its vhost DocumentRoot uses `/home/devsys/office-deploy/current/public`.

The active release's `src/Views/Company/form.twig` has SHA-256 `6d3a247cffa5ad70f61f920e8a72777590ddb8ec796a0695029b27d3595efe2c`, matching `office/main`. It contains all three note controls, labels, field names, and `maxlength=2000`. The preceding release `office-mvp-pdf-20261125-dfaa435` has no PMD control in its template. A synthetic Twig render from the active release, passed through `Response::setHtml()->toJson()`, retained all three IDs in the response HTML (7,249 bytes).

The defect is in the browser window lifecycle, not the template or response assembly. `Company::form()` asks `Core.callAction()` for the form. If `Company_form` is already present in `appState.openWindows`, `callAction()` only activates that in-memory window and returns without requesting the current form. A window left open from the prior release therefore keeps the old form markup, which has no PMD/PMT/AAB controls. A Node harness using the actual `Company` and `Core` modules reproduced this: the existing stale window was activated, fetch count stayed at zero, and its DOM remained without the note controls.

No authenticated browser session was available to capture the user's actual fragment response or DOM. Production runtime was inspected read-only; the exact user session state could not be observed. The code path, active/previous release templates, and isolated reproduction establish the stale in-memory window as the concrete cause consistent with the reported symptom. This was not a CSS visibility issue.

## Change

`Company::form()` now checks an existing settings window for all three coded-note controls. It refreshes a clean stale window through the existing authenticated `Company/form` action and binds the submit handler after replacement. If the stale form has unsaved edits, it is preserved and the user receives a warning instead of having their edits discarded. Existing complete windows are simply reactivated. No endpoint, SDK, API, database, migration, CSS, or legal text changed.

Added `tests/JavaScript/company-form-stale-window.test.mjs` to exercise both stale-window refresh and preservation of dirty form content. The response rendering check confirms the final JSON HTML contains all three expected controls; no production session or data was used.

## Verification

| Check | Result |
|---|---|
| `composer test` | PASS — 177 tests, 552 assertions |
| `npm run build` | PASS |
| `git diff --check` | PASS |
| `node --experimental-default-type=module tests/JavaScript/company-form-stale-window.test.mjs` | PASS — stale clean window refreshes; dirty content remains intact |
| Synthetic active-release Twig render → response JSON HTML | PASS — PMD/PMT/AAB IDs present |
| Authenticated production fragment / browser DOM | NOT_RUN — no authenticated browser session available; no OIDC bypass attempted |

## Production state and next check

No production files, services, releases, database, flags, company settings, invoices, or archives were changed. The current Office release already contains the correct Twig controls. For the affected open session, close the existing Société window and reopen it, or reload the Office page; the active server template then includes the controls. The code fix prevents a clean stale Company window from silently remaining when its form is requested again.

After review and a separate deployment authorization, verify that all three fields appear, save only operator-approved text, close and reopen Société to confirm persistence, and check the other company settings sections. No invoice needs to be created or issued for this UI check. Any Apache reload in the eventual deployment procedure is global and also rereads the login vhost; none was performed here.
