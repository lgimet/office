export class FormDirtyTracker {

    #dirty = false;

    constructor(form, tab) {
        this.form = form;
        this.tab = tab;
        this.tabIndicator = this.tab.querySelector(".tab-dirty-indicator");
        
        this.initialState = this._serialize();

        this._bindEvents();
    }
    get dirty() {
        return this.#dirty;
    }
    _bindEvents() {

        this.form.addEventListener('input', () => this._check());
        this.form.addEventListener('change', () => this._check());
    }

    _serialize() {
        const formData = new FormData(this.form);
        return JSON.stringify([...formData.entries()]);
    }

    _check() {
        const current = this._serialize();

        if (current !== this.initialState) {
            this.tabIndicator.classList.add('visible');
            this.tab.classList.add("dirty");
            this.#dirty = true;

        } else {
            this.tabIndicator.classList.remove('visible');
            this.tab.classList.remove("dirty");
            this.#dirty = false;
        }
    }

    reset() {
        this.initialState = this._serialize();
        this.tabIndicator.classList.remove('visible');
        this.tab.classList.remove("dirty");
        this.#dirty = false;
    }
}
