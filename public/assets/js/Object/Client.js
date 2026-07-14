import { Core } from "../Core/Core.js";
import { FormValidator } from "../Core/FormValidator.js";

export default class Client extends Core {
    static #instance = null;

    static instance() {
        if (!Client.#instance) {
            Client.#instance = new Client();
        }

        return Client.#instance;
    }

    form() {
        this.actions = [
            {
                label: "Annuler",
                className: "m-btn m-btn--outline",
                callback: () => {
                    const form = this.bodyRef?.client_form;
                    form?.reset();
                }
            },
            {
                label: "Sauvegarder",
                className: "m-btn m-btn--primary",
                callback: (windowRecord) => {
                    const form = this.bodyRef?.client_form;
                    
                    if (!form) return;
                    form.requestSubmit();
                }
            }
        ];

        this.callAction(this.form.name, {}, {
            callback: (_json) => {
                const form = this.bodyRef?.client_form;
                if (!form || form.dataset.submitBound === "true") return;

                form.dataset.submitBound = "true";
                form.addEventListener("submit", (e) => {
                    e.preventDefault();

                    const validator = FormValidator.init(form);
                    if (!validator.validateForm()) {
                        return;
                    }
                    this.update();
                });
            }
        });
    }
    update() {
        
        const vars = this.collectFormData(this.bodyRef?.client_form);
        this.callAction(this.update.name, {vars}, {
            callback: (_json) => {
                UI.toast(_json.toast, "ok");
            }
        });
    }
}
