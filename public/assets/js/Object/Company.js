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

        this.callAction("form", {}, {
            callback: (json) => {
                if (!json.success) {
                    UI.toast(json.error.message, "error");
                    return;
                }

                const form = this.bodyRef.company_form;
                form.addEventListener("submit", (event) => {
                    event.preventDefault();
                    this.save();
                });
            },
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
