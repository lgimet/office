import { Core } from "../Core/Core.js";
import { DynamicTable } from "../ui/DynamicTable.js";
import { UI } from "../ui/UI.js";

export default class Invoices extends Core {
    idName = "id";

    list() {
        this.actions = [
            {
                label: "Ajouter une facture",
                className: "m-btn m-btn--primary",
                callback: () => this.form(),
            },
        ];

        this.callAction("list", {}, {
            callback: (json) => {
                if (!json.success) return;

                const isDraft = (invoice) => (
                    String(invoice.status || "").trim().toLowerCase() === "draft"
                );

                const table = this.table = new DynamicTable("invoices-table", {
                    searchInput: this.bodyRef.invoices_search,
                    filters: { status: this.bodyRef.invoices_status },
                    actions: [
                        {
                            name: "edit",
                            label: "Modifier le brouillon",
                            icon: "bi-pencil-fill",
                            visible: isDraft,
                        },
                        {
                            name: "delete",
                            label: "Supprimer le brouillon",
                            icon: "bi-trash3-fill",
                            visible: isDraft,
                        },
                        {
                            name: "view",
                            label: "Voir la facture",
                            icon: "bi-eye-fill",
                            visible: (invoice) => !isDraft(invoice),
                        },
                    ],
                    formatters: {
                        status: (value) => {
                            const label = {
                                draft: "Brouillon",
                                issued: "Émise",
                                cancelled: "Annulée",
                            }[value] || value;
                            const className = value === "draft" ? "is-inactive" : "is-active";

                            return `<span class="status-badge ${className}">${label}</span>`;
                        },
                    },
                });

                table.on("edit", (event) => this.form(event.detail.id));
                table.on("delete", (event) => this.confirmDelete(event.detail.id));
                table.on("view", (event) => this.view(event.detail.id));
            },
        });
    }

    form(id = null) {
        this.actions = this.formActions(id);

        this.callAction("form", { id }, {
            callback: (json) => {
                if (!json.success) {
                    UI.toast(json.error.message, "error");
                    return;
                }

                const form = this.bodyRef.invoice_form;
                const lines = this.bodyRef.invoice_lines;

                form.addEventListener("submit", (event) => {
                    event.preventDefault();
                    this.save();
                });

                lines.querySelectorAll(".invoice-line-row").forEach((row) => this.bindLine(row));

                if (!lines.querySelector(".invoice-line-row")) {
                    this.addLine();
                }

                this.bodyRef.add_invoice_line.addEventListener("click", () => this.addLine());
                this.bindClientDetails();
                this.bindPaymentTerms();
                this.recalculate();
            },
        });
    }

    formActions(id = null) {
        const actions = [];

        if (id) {
            actions.push({
                label: "Supprimer le brouillon",
                className: "m-btn m-btn--outline",
                icon: "bi-trash3-fill",
                callback: (record) => this.confirmDelete(id, record),
            });
        }

        actions.push({
            label: "Enregistrer le brouillon",
            className: "m-btn m-btn--primary",
            icon: "bi-floppy",
            callback: () => this.save(),
        });

        if (id) {
            actions.push({
                label: "Émettre la facture",
                className: "m-btn m-btn--primary",
                icon: "bi-send-check",
                callback: (record) => this.issue(id, record),
            });
        }

        return actions;
    }

    addLine() {
        const row = document.createElement("div");
        const defaultTaxRate = this.bodyRef.invoice_form.dataset.defaultTaxRate || "20";

        row.className = "invoice-line-row";
        row.innerHTML = `
            <div class="invoice-line">
                <span class="line-position"></span>
                <input name="label" placeholder="Désignation">
                <input name="description" placeholder="Description">
                <input name="quantity" type="number" min="0.001" step="0.001" value="1">
                <select name="unit">
                    <option value="unité">unité</option>
                    <option value="heure">heure</option>
                    <option value="jour">jour</option>
                    <option value="forfait">forfait</option>
                    <option value="mois">mois</option>
                </select>
                <input name="unit_price_excl_tax" type="number" min="0" step="0.01" value="0">
                <input name="tax_rate" type="number" min="0" step="0.01" value="${defaultTaxRate}">
                <strong class="line-total">0,00 €</strong>
                <div class="line-actions">
                    <button type="button" class="invoice-discount-toggle">+ Remise</button>
                    <button type="button" class="invoice-line-delete" aria-label="Supprimer cette ligne">×</button>
                </div>
            </div>
            <div class="invoice-discount" hidden>
                <span class="invoice-discount__title">Remise</span>
                <label>
                    <span>Type</span>
                    <select name="discount_type">
                        <option value="">Aucune remise</option>
                        <option value="percentage">Pourcentage</option>
                        <option value="fixed">Montant fixe</option>
                    </select>
                </label>
                <label>
                    <span>Valeur</span>
                    <div class="invoice-discount__value">
                        <input name="discount_value" type="number" min="0" step="0.01" value="0">
                        <span class="discount-suffix">%</span>
                    </div>
                </label>
                <label class="invoice-discount__comment">
                    <span>Commentaire de remise</span>
                    <input name="discount_note" maxlength="255" placeholder="Ex. geste commercial">
                </label>
                <span class="invoice-discount__amount">
                    Montant déduit : <strong class="line-discount-amount">0,00 €</strong>
                </span>
                <button type="button" class="invoice-discount-remove">Retirer la remise</button>
            </div>
        `;

        this.bodyRef.invoice_lines.appendChild(row);
        this.bindLine(row);
        this.updateLinePositions();
        this.recalculate();
    }

    bindLine(row) {
        const discount = row.querySelector(".invoice-discount");
        const discountType = row.querySelector('[name="discount_type"]');
        const discountValue = row.querySelector('[name="discount_value"]');

        row.querySelector(".invoice-line-delete").addEventListener("click", () => {
            row.remove();
            this.updateLinePositions();
            this.recalculate();
        });

        row.querySelector(".invoice-discount-toggle").addEventListener("click", () => {
            discount.hidden = !discount.hidden;

            if (!discount.hidden && !discountType.value) {
                discountType.value = "percentage";
            }

            this.updateDiscountDisplay(row);
            this.recalculate();
        });

        row.querySelector(".invoice-discount-remove").addEventListener("click", () => {
            discount.hidden = true;
            discountType.value = "";
            discountValue.value = "0";
            row.querySelector('[name="discount_note"]').value = "";
            this.updateDiscountDisplay(row);
            this.recalculate();
        });

        row.querySelectorAll("input, select").forEach((field) => {
            field.addEventListener("input", () => this.recalculate());
            field.addEventListener("change", () => this.recalculate());
        });

        this.updateDiscountDisplay(row);
    }

    updateLinePositions() {
        this.bodyRef.invoice_lines
            .querySelectorAll(".invoice-line-row .line-position")
            .forEach((position, index) => {
                position.textContent = index + 1;
            });
    }

    recalculate() {
        let subtotalHt = 0;
        let totalDiscount = 0;
        let totalTax = 0;

        this.bodyRef.invoice_lines.querySelectorAll(".invoice-line-row").forEach((row) => {
            const quantity = Number(row.querySelector('[name="quantity"]').value) || 0;
            const unitPrice = Number(row.querySelector('[name="unit_price_excl_tax"]').value) || 0;
            const taxRate = Number(row.querySelector('[name="tax_rate"]').value) || 0;
            const discount = this.discountAmount(row, quantity * unitPrice);
            const lineTotal = Math.max(0, (quantity * unitPrice) - discount);

            subtotalHt += quantity * unitPrice;
            totalDiscount += discount;
            totalTax += lineTotal * taxRate / 100;
            row.querySelector(".line-total").textContent = this.formatAmount(lineTotal);
            row.querySelector(".line-discount-amount").textContent = this.formatAmount(discount);
            this.updateDiscountDisplay(row);
        });

        const totalHt = subtotalHt - totalDiscount;
        const hasDiscount = totalDiscount > 0;

        this.bodyRef.invoice_summary_subtotal.hidden = !hasDiscount;
        this.bodyRef.invoice_summary_discount.hidden = !hasDiscount;
        this.bodyRef.invoice_total_subtotal.textContent = this.formatAmount(subtotalHt);
        this.bodyRef.invoice_total_discount.textContent = `-${this.formatAmount(totalDiscount)}`;
        this.bodyRef.invoice_total_ht.textContent = this.formatAmount(totalHt);
        this.bodyRef.invoice_total_tax.textContent = this.formatAmount(totalTax);
        this.bodyRef.invoice_total_ttc.textContent = this.formatAmount(totalHt + totalTax);
    }

    discountAmount(row, grossAmount) {
        const discount = row.querySelector(".invoice-discount");

        if (discount.hidden) return 0;

        const type = row.querySelector('[name="discount_type"]').value;
        const value = Number(row.querySelector('[name="discount_value"]').value) || 0;

        if (type === "percentage") {
            return Math.max(0, Math.min(grossAmount, grossAmount * value / 100));
        }

        if (type === "fixed") {
            return Math.max(0, Math.min(grossAmount, value));
        }

        return 0;
    }

    updateDiscountDisplay(row) {
        const discount = row.querySelector(".invoice-discount");
        const type = row.querySelector('[name="discount_type"]').value;
        const toggle = row.querySelector(".invoice-discount-toggle");

        row.querySelector(".discount-suffix").textContent = type === "fixed" ? "€" : "%";
        toggle.textContent = discount.hidden ? "+ Remise" : "Remise active";
        toggle.classList.toggle("is-active", !discount.hidden);
    }

    bindClientDetails() {
        const form = this.bodyRef.invoice_form;
        const client = form.querySelector("#invoice-client");
        const hidden = client?.querySelector("input[name='client_id']");
        const fields = {
            address: form.querySelector("#invoice-client-address"),
            postalCode: form.querySelector("#invoice-client-postal-code"),
            city: form.querySelector("#invoice-client-city"),
            phone: form.querySelector("#invoice-client-phone"),
            email: form.querySelector("#invoice-client-email"),
        };

        if (!hidden || Object.values(fields).some((field) => !field)) return;

        const render = (data = {}) => {
            fields.address.textContent = [data.address_line1, data.address_line2]
                .filter(Boolean)
                .join(", ");
            fields.postalCode.textContent = data.postal_code || "";
            fields.city.textContent = data.city || "";
            fields.phone.textContent = data.phone || "";
            fields.email.textContent = data.email || "";
            fields.email.href = data.email ? `mailto:${data.email}` : "";
        };

        hidden.addEventListener("change", async () => {
            const id = Number(hidden.value);
            if (!id) {
                render();
                return;
            }

            try {
                const response = await fetch(`/Invoices/clientOptions?id=${encodeURIComponent(id)}`, {
                    credentials: "same-origin",
                });
                const payload = await response.json();
                render(payload.data?.[0] || {});
            } catch (error) {
                console.error("Client details load error", error);
            }
        });
    }

    bindPaymentTerms() {
        const form = this.bodyRef.invoice_form;
        const preset = form.querySelector("#invoice-payment-terms-preset");
        const value = form.querySelector("#invoice-payment-terms");
        const code = form.querySelector("#invoice-payment-terms-code");
        const customWrapper = form.querySelector(".invoice-payment-terms-custom");
        const customInput = form.querySelector("#invoice-payment-terms-custom-input");
        const issueDate = form.querySelector("#invoice-issue-date");
        const dueDate = form.querySelector("#invoice-due-date");

        if (!preset || !value || !code || !customWrapper || !customInput || !issueDate || !dueDate) return;

        const normalize = (text) => String(text || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim()
            .toLowerCase();
        const existingValue = value.value;
        const legacyLabels = {
            "30 jours puis fin de mois": "days_30_then_eom",
            "45 jours puis fin de mois": "days_45_then_eom",
        };
        const codeOption = [...preset.options].find((option) => option.value === code.value);
        const labelOption = [...preset.options].find((option) => (
            normalize(option.dataset.label) === normalize(existingValue)
            || legacyLabels[normalize(existingValue)] === option.value
            || (normalize(existingValue) === "sous 15 jours" && option.value === "days_15")
        ));
        const matchingOption = codeOption || labelOption;

        if (matchingOption) {
            preset.value = matchingOption.value;
            customInput.value = matchingOption.value === "custom"
                ? existingValue
                : matchingOption.dataset.label;
        } else {
            preset.value = "custom";
            customInput.value = existingValue;
        }

        const syncState = (recalculateDueDate = false) => {
            const option = preset.options[preset.selectedIndex];
            const isCustom = preset.value === "custom";

            customWrapper.hidden = !isCustom;
            customInput.required = isCustom;
            dueDate.readOnly = !isCustom;

            if (!isCustom) {
                value.value = option?.dataset.label || "";
                code.value = preset.value;
                if (recalculateDueDate) this.updateDueDate(preset.value, issueDate, dueDate);
            } else {
                code.value = "custom";
                value.value = customInput.value;
            }
        };

        preset.addEventListener("change", () => syncState(true));
        issueDate.addEventListener("change", () => {
            if (preset.value !== "custom") this.updateDueDate(preset.value, issueDate, dueDate);
        });
        customInput.addEventListener("input", () => {
            value.value = customInput.value;
        });

        syncState(false);
        if (form.dataset.existingInvoice !== "1" && !dueDate.value && preset.value !== "custom") {
            this.updateDueDate(preset.value, issueDate, dueDate);
        }
    }

    updateDueDate(rule, issueDate, dueDate) {
        const calculated = this.calculateDueDate(issueDate.value, rule);
        if (calculated) dueDate.value = calculated;
    }

    calculateDueDate(issueDate, rule) {
        const parts = String(issueDate || "").split("-").map(Number);
        if (parts.length !== 3 || parts.some((part) => !Number.isInteger(part))) return "";

        const [year, month, day] = parts;
        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return "";

        const addDays = (days) => date.setDate(date.getDate() + days);
        const endOfMonth = () => date.setMonth(date.getMonth() + 1, 0);

        switch (rule) {
            case "cash":
            case "receipt":
                break;
            case "days_15":
                addDays(15);
                break;
            case "days_30":
                addDays(30);
                break;
            case "days_45":
                addDays(45);
                break;
            case "days_60":
                addDays(60);
                break;
            case "days_30_then_eom":
                addDays(30);
                endOfMonth();
                break;
            case "days_45_then_eom":
                addDays(45);
                endOfMonth();
                break;
            default:
                return "";
        }

        const pad = (number) => String(number).padStart(2, "0");
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    save() {
        const form = this.bodyRef.invoice_form;

        if (!form.reportValidity()) return;

        const data = this.collectFormData(form);
        data.id = data.id || null;
        data.lines = [...this.bodyRef.invoice_lines.querySelectorAll(".invoice-line-row")].map((row) => (
            Object.fromEntries(
                [...row.querySelectorAll("input, select")].map((field) => [field.name, field.value])
            )
        ));

        this.callAction("save", data, {
            callback: (json) => {
                if (json.success) {
                    UI.toast(json.toast, "ok");

                    if (json.id) {
                        form.querySelector('[name="id"]').value = json.id;

                        const parentWindow = form.closest(".window");
                        const windowId = parentWindow ? parentWindow.id : null;
                        const record = this.resolveWindowRecord(windowId);

                        if (record) {
                            record.resourceId = json.id;
                            record.actions = this.formActions(json.id);
                            this.renderWindowFooter(record);
                        }
                    }

                    if (this.table) {
                        this.table.load(true);
                    }
                    return;
                }

                UI.toast(json.error.message, "error");
            },
        });
    }

    async issue(id, windowRecord = null) {
        const form = this.bodyRef.invoice_form;

        if (!form.reportValidity()) return;

        const confirmed = await UI.confirm({
            type: "warning",
            message: "Émettre cette facture ?<br><br>Un numéro définitif va lui être attribué. Après émission, elle ne pourra plus être modifiée ni supprimée comme un brouillon.",
            yesText: "Émettre",
            noText: "Annuler",
        });

        if (!confirmed) return;

        const data = this.collectFormData(form);
        data.id = id;
        data.lines = [...this.bodyRef.invoice_lines.querySelectorAll(".invoice-line-row")].map((row) => (
            Object.fromEntries(
                [...row.querySelectorAll("input, select")].map((field) => [field.name, field.value])
            )
        ));

        this.callAction("issue", data, {
            callback: (json) => {
                if (!json.success) {
                    UI.toast(json.error.message, "error");
                    return;
                }

                UI.toast(json.toast, "ok");
                if (this.table) this.table.load(true);

                const record = windowRecord || this.resolveWindowRecord(form.closest(".window")?.id);
                if (record) this.closeWindow(record.id);
                this.view(id);
            },
        });
    }

    view(id) {
        this.actions = [];
        this.callAction("view", { id });
    }

    async confirmDelete(id, windowRecord = null) {
        const confirmed = await UI.confirm({
            type: "warning",
            message: "Voulez-vous supprimer définitivement ce brouillon et ses lignes ?",
            yesText: "Supprimer",
            noText: "Annuler",
        });

        if (!confirmed) return;

        this.callAction("delete", { id }, {
            callback: (json) => {
                if (!json.success) {
                    UI.toast(json.error.message, "error");
                    return;
                }

                UI.toast(json.toast, "ok");

                if (this.table) {
                    this.table.load(true);
                }

                if (windowRecord) {
                    this.closeWindow(windowRecord.id);
                }
            },
        });
    }

    formatAmount(value) {
        return `${value.toFixed(2).replace(".", ",")} €`;
    }
}
