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
                this.recalculate();
            },
        });
    }

    formActions(id = null) {
        const actions = [
            {
                label: "Annuler",
                className: "m-btn m-btn--outline",
                icon: "bi-x-lg",
                callback: () => this.list(),
            },
        ];

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
