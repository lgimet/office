import { Core } from "../Core/Core.js";
import { DynamicTable } from "../ui/DynamicTable.js";
import { UI } from "../ui/UI.js";

export default class Clients extends Core {
    list() {
        this.actions = [
            {
                label: "Ajouter un client",
                className: "m-btn m-btn--primary",
                callback: () => this.form(),
            },
        ];

        this.callAction("list", {}, {
            callback: (json) => {
                if (!json.success) return;

                this.table = new DynamicTable("clients-table", {
                    actions: [
                        {
                            name: "edit",
                            label: "Voir ou modifier le client",
                        },
                    ],
                    searchInput: this.bodyRef.clients_search,
                    filters: {
                        client_category: this.bodyRef.clients_category_filter,
                        legal_type: this.bodyRef.clients_legal_type_filter,
                    },
                    formatters: {
                        status: (value) => {
                            const labels = {
                                active: "Actif",
                                inactive: "Inactif",
                                prospect: "Prospect",
                                archived: "Archivé",
                            };
                            const className = value === "active" ? "is-active" : "is-inactive";

                            return `<span class="status-badge ${className}">${labels[value] || value}</span>`;
                        },
                    },
                    onError: () => {
                        UI.toast("La liste des clients est temporairement indisponible.", "error");
                    },
                });

                this.table.on("edit", (event) => this.form(event.detail.id));
            },
        });
    }

    form(id = null) {
        this.actions = [
            {
                label: "Annuler",
                className: "m-btn m-btn--outline",
                callback: () => this.list(),
            },
            {
                label: "Enregistrer",
                className: "m-btn m-btn--primary",
                callback: () => this.save(),
            },
        ];

        this.callAction("form", { id }, {
            callback: () => {
                const form = this.bodyRef.client_form;

                if (!form) return;

                form.addEventListener("submit", (event) => {
                    event.preventDefault();
                    this.save();
                });
            },
        });
    }

    save() {
        const form = this.bodyRef.client_form;

        if (!form || !form.reportValidity()) return;

        this.callAction("save", this.collectFormData(form), {
            callback: (json) => {
                if (!json.success) {
                    this.showValidationErrors(form, json.data?.errors || {});
                    UI.toast(json.error.message, "error");
                    return;
                }

                this.clearValidationErrors(form);

                UI.toast(json.toast || "Client enregistré.", "ok");

                const etag = json.data?.etag;
                const etagInput = form.elements.etag;

                if (etag && etagInput) {
                    etagInput.value = etag;
                }

                if (this.table) {
                    this.table.load(true);
                }
            },
        });
    }

    showValidationErrors(form, errors) {
        this.clearValidationErrors(form);

        Object.entries(errors).forEach(([fieldName, messages]) => {
            const field = form.elements[fieldName];
            const formField = field?.closest(".form-field");

            if (!formField) return;

            formField.classList.add("is-invalid");
            const error = document.createElement("span");
            error.className = "form-error-inline show";
            error.textContent = Array.isArray(messages) ? messages[0] : messages;
            formField.appendChild(error);
        });
    }

    clearValidationErrors(form) {
        form.querySelectorAll(".form-field.is-invalid").forEach((field) => {
            field.classList.remove("is-invalid");
        });
        form.querySelectorAll(".form-error-inline").forEach((error) => error.remove());
    }
}
