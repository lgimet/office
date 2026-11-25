import { Core } from "../Core/Core.js";
import { UI } from "../ui/UI.js";

export default class Company extends Core {
    form() {
        this.actions = [
            {
                label: "Annuler",
                className: "m-btn m-btn--outline",
                icon: "bi-x-lg",
                callback: (record) => this.closeWindow(record.id),
            },
            {
                label: "Enregistrer",
                className: "m-btn m-btn--primary",
                icon: "bi-floppy",
                callback: () => this.save(),
            },
        ];

        const windowId = this.buildWindowId(this.objectName, null, "form");
        const existingWindow = this.resolveWindowRecord(windowId);

        if (existingWindow) {
            this.activateWindow(windowId);

            const hasCodedNotes = [
                "company-invoice-note-pmd",
                "company-invoice-note-pmt",
                "company-invoice-note-aab",
            ].every((id) => existingWindow.body.querySelector(`#${id}`));

            if (!hasCodedNotes) {
                if (existingWindow.dirtyTracker?.dirty) {
                    UI.toast(
                        "Ce formulaire ancien contient des modifications non enregistrées. Enregistrez-les ou fermez-le avant de le recharger.",
                        "warning"
                    );
                    return;
                }

                this.callAction("form", {}, {
                    refresh: true,
                    windowId,
                    callback: (json) => this.handleFormResponse(json, windowId),
                });
            }

            return;
        }

        this.callAction("form", {}, {
            callback: (json) => this.handleFormResponse(json, windowId),
        });
    }

    handleFormResponse(json, windowId) {
        if (!json.success) {
            UI.toast(json.error.message, "error");
            return;
        }

        const form = this.resolveWindowRecord(windowId)?.bodyRef?.company_form;
        if (!form) {
            UI.toast("Le formulaire Société n’a pas pu être chargé. Réessayez.", "error");
            return;
        }

        form.addEventListener("submit", (event) => {
            event.preventDefault();
            this.save();
        });
    }

    save() {
        const form = this.bodyRef.company_form;

        if (!form.reportValidity()) return;

        this.callAction("save", this.collectFormData(form), {
            callback: (json) => {
                if (json.success) {
                    UI.toast(json.toast, "ok");
                    return;
                }

                UI.toast(json.error.message, "error");
            },
        });
    }
}
