import assert from "node:assert/strict";
import Company from "../../public/assets/js/Object/Company.js";
import { appState } from "../../public/assets/js/Core/AppState.js";

const noteIds = [
    "company-invoice-note-pmd",
    "company-invoice-note-pmt",
    "company-invoice-note-aab",
];
const completeForm = `<form id="company_form">${noteIds.map((id) => `<textarea id="${id}"></textarea>`).join("")}</form>`;
const staleForm = '<form id="company_form"><fieldset><legend>Mention de bas de facture</legend></fieldset></form>';

function makeBody(initialHtml) {
    const form = { listeners: [], addEventListener(type, callback) { this.listeners.push({ type, callback }); } };
    return {
        innerHTML: initialHtml,
        querySelector(selector) {
            if (selector === "#company_form") return form;
            return noteIds.includes(selector.slice(1)) && this.innerHTML.includes(selector.slice(1)) ? {} : null;
        },
        querySelectorAll() { return []; },
        form,
    };
}

function makeWindow(dirty = false) {
    const body = makeBody(staleForm);
    const classList = { add() {}, remove() {} };
    return {
        id: "Company_form",
        tab: { classList, getBoundingClientRect: () => ({ right: 10 }), querySelector: () => ({ classList }) },
        window: { classList },
        body,
        bodyRef: { company_form: body.form },
        dirtyTracker: { dirty },
    };
}

const tabsBar = { scrollLeft: 0, getBoundingClientRect: () => ({ right: 100 }) };
globalThis.document = {
    getElementById: (id) => id === "tabsBar" ? tabsBar : null,
    createElement: () => ({ classList: { add() {}, remove() {} } }),
    body: { appendChild() {} },
};
globalThis.window = { CSRF_TOKEN: "test" };
const originalSetTimeout = globalThis.setTimeout;
globalThis.setTimeout = () => 0;

try {
    appState.openWindows.clear();
    const staleRecord = makeWindow();
    appState.openWindows.set(staleRecord.id, staleRecord);
    let fetchCalls = 0;
    globalThis.fetch = async (_url, options) => {
        fetchCalls++;
        assert.equal(options.method, "POST");
        assert.equal(JSON.parse(options.body).csrf, "test");
        return { json: async () => ({ success: true, object: "Company", action: "form", html: completeForm }) };
    };

    new Company().form();
    await new Promise((resolve) => originalSetTimeout(resolve, 0));
    assert.equal(fetchCalls, 1, "a stale clean window should refetch its form");
    for (const id of noteIds) assert.ok(staleRecord.body.querySelector(`#${id}`), `${id} should exist after refresh`);
    assert.equal(staleRecord.body.form.listeners.filter(({ type }) => type === "submit").length, 1);

    appState.openWindows.clear();
    const dirtyRecord = makeWindow(true);
    appState.openWindows.set(dirtyRecord.id, dirtyRecord);
    fetchCalls = 0;
    new Company().form();
    assert.equal(fetchCalls, 0, "a dirty stale form must not be replaced or refetched");
    assert.equal(dirtyRecord.body.innerHTML, staleForm, "unsaved form content must be preserved");
    console.log("PASS: stale clean Company form refreshes to include PMD/PMT/AAB; dirty form is preserved");
} finally {
    globalThis.setTimeout = originalSetTimeout;
}
