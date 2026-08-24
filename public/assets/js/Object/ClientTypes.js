import { Core } from "../Core/Core.js";
import { DynamicTable } from "../ui/DynamicTable.js";
import { UI } from "../ui/UI.js";

export default class ClientTypes extends Core {
    list() {
        this.actions = [{ label: 'Ajouter un type de client', className: 'm-btn m-btn--primary', callback: () => this.openDialog() }];
        this.callAction('list', {}, { callback: (json) => {
            if (!json.success) return;
            this.table = new DynamicTable('client-types-table', { actions: [{ name: 'edit', label: 'Modifier', icon: 'bi-pencil-fill' }, { name: 'toggle', label: 'Activer ou désactiver', icon: 'bi-power' }, { name: 'delete', label: 'Supprimer', icon: 'bi-trash3-fill' }], formatters: { is_active: value => `<span class="status-badge ${Number(value) ? 'is-active' : 'is-inactive'}">${Number(value) ? 'Actif' : 'Inactif'}</span>` } });
            this.table.on('edit', (event) => this.openDialog(this.table.getRowById(event.detail.id)?._data));
            this.table.on('toggle', (event) => this.request('toggle', event.detail.id));
            this.table.on('delete', (event) => { if (confirm('Supprimer ce type de client ?')) this.request('delete', event.detail.id); });
        }});
    }
    openDialog(row = null) {
        const dialog = this.bodyRef.client_type_dialog, form = this.bodyRef.client_type_form;
        form.reset();
        if (row?.id) Object.entries(row).forEach(([key, value]) => { if (form.elements[key]) form.elements[key].value = value ?? ''; });
        form.elements.is_active.checked = row ? Number(row.is_active) === 1 : true;
        form.querySelectorAll('.form-field input:not([type="checkbox"]):not([type="hidden"]), .form-field textarea').forEach((field) => {
            field.closest('.form-field')?.classList.toggle('has-value', field.value !== '');
        });
        form.elements.name.oninput = () => { if (!form.elements.slug.value) form.elements.slug.value = form.elements.name.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''); };
        dialog.showModal();
        form.onsubmit = (event) => { event.preventDefault(); this.callAction('save', this.collectFormData(form), { callback: (json) => { if (json.success) { dialog.close(); UI.toast(json.toast, 'ok'); this.table.load(true); } else UI.toast(json.error.message, 'error'); } }); };
    }
    request(action, id) { this.callAction(action, { id }, { callback: (json) => { if (json.success) { UI.toast(json.toast, 'ok'); this.table.load(true); } else UI.toast(json.error.message, 'warning'); } }); }
}
