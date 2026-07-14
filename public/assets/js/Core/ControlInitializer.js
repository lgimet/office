import { CustomSelect } from "../ui/CustomSelect.js";
import { CustomMultiSelect } from "../ui/CustomMultiSelect.js";
import { InputMask } from "./InputMask.js";
import { BankField } from "../ui/BankField.js";
import { FormDirtyTracker } from "./FormDirtyTracker.js";
import { FormValidator } from "./FormValidator.js";

export function initControls(root, options = {}) {
    if (!root) return null;

    root.querySelectorAll(".select").forEach((element) => {
        if (element.dataset.initialized === "true") return;
        element.dataset.initialized = "true";

        new CustomSelect({
            element,
            url: element.dataset.url,
            mode: element.dataset.mode,
            placeholder: element.dataset.placeholder
        });
    });

    root.querySelectorAll(".custom-multi-select").forEach((element) => {
        if (element.dataset.initialized === "true") return;
        element.dataset.initialized = "true";
        new CustomMultiSelect(element);
    });

    root.querySelectorAll('input[required], select[required], textarea[required]').forEach((field) => {
        const fieldId = field.id || field.name;
        if (!fieldId) return;

        const label = root.querySelector(`label[for="${fieldId}"]`);
        if (!label || label.querySelector('.required')) return;

        const marker = document.createElement('span');
        marker.className = 'required';
        marker.textContent = '*';
        label.appendChild(marker);
    });

    root.querySelectorAll("[data-mask]").forEach((input) => {
        if (input.dataset.maskInitialized === "true") return;
        input.dataset.maskInitialized = "true";
        new InputMask(input);
    });

    root.querySelectorAll("[data-bank]").forEach((element) => {
        if (element.dataset.bankInitialized === "true") return;
        element.dataset.bankInitialized = "true";
        new BankField(element);
    });

    root.querySelectorAll("form:not([nodirty])").forEach((form) => {
        if (form.dataset.validatorInitialized !== "true") {
            form.dataset.validatorInitialized = "true";
            FormValidator.init(form);
        }
    });

    if (!options.tab) return null;

    const trackedForm = root.querySelector('form:not([nodirty])');
    if (!trackedForm) return null;

    return new FormDirtyTracker(trackedForm, options.tab);
}
