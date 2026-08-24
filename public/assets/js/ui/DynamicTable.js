export class DynamicTable extends EventTarget {

	constructor(container, options = {}) {
		super();
		this.container = document.getElementById(container);
		if( !this.container ) return;

		this.icons = {
			"delete" : "bi-trash3-fill",
			"edit" : "bi-pencil-fill"
		}
		this.options = {
			actions: [],
			searchInput: null,
			filters: {},
			formatters: {},
			onError: null,
			emptyMessage: 'Aucun résultat.',
			callbackDblClick: null,
			callbackClick: null,
			...options
		}
		this.body = this.container.querySelector(".flex-table-body");
		this.model = this.body.querySelector(".model");

		this.source = this.container.dataset.source;
		this.limit = parseInt(this.container.dataset.limit) || 10;
		this.columns = this.container.dataset.columns.split(",");
		this.widths = this.container.dataset.widths ? this.container.dataset.widths.split(",") : []; 
		this.idsearch = this.container.dataset.idsearch || 0;

		this.sort = this.container.dataset.sort || this.columns[0];
		this.dir = this.container.dataset.dir || "asc";
		this.page = 1;
		this.totalPages = 1;
		this.loading = false;
		this.finished = false;
		this.createSentinel();
		this.createObserver();
		this.initSorting();
		this.initEvents();
		this.initFilters();
		const header = this.container.querySelector(".flex-table-header");
		this.applyGridTemplate(header);
		this.load(true);
		this.updateSortIcons();
	}
	emit(action, id) {
		this.dispatchEvent(
			new CustomEvent(action, {
				detail: { action, id }
			})
		);
	}
	applyGridTemplate(row) {
		if (this.widths.length) {
			row.style.gridTemplateColumns = this.widths.join(" ");
		}
	}
	initFilters() {
		if (this.options.searchInput) {
			this.options.searchInput.addEventListener("input", (e) => {
				this.load(true);
			});
		}
		Object.values(this.options.filters).forEach((input) => {
			const filterInput = this.getFilterInput(input);
			if (filterInput) filterInput.addEventListener('change', () => this.load(true));
		});
	}
	initEvents() {

		this.body.addEventListener("click", (e) => {

			const btn = e.target.closest(".btn-action");
			if (!btn) return;

			const row = btn.closest(".flex-row");
			const id = row.dataset.id;
			const actionName = btn.dataset.action;
			const action = this.options.actions.find(a => a.name === actionName);
			
			this.emit(actionName,id);
			//if (action && typeof action.callback === "function") {
			//	action.callback(id, row);
			//}

		});
		this.onEvent('refreshRow',(e) => {
			this.refreshRow(e.detail)
		})
	}
	async load(reset = false) {
		if (reset) {
			this.page = 1;
			this.finished = false;
			this.clearRows();
		}
		if (this.loading || this.finished) return;

		this.loading = true;

		const params = new URLSearchParams({
			page: this.page,
			limit: this.limit,
			sort: this.sort,
			dir: this.dir,
			columns: this.columns,
			idsearch: this.idsearch
		});
		 if (this.options.searchInput) params.append("search", this.options.searchInput.value);
		Object.entries(this.options.filters).forEach(([name, input]) => {
			const filterInput = this.getFilterInput(input);
			params.append(name, filterInput ? filterInput.value : '');
		});

		try {
			const response = await fetch(`${this.source}?${params}`);
			const result = await response.json();
			if (!result.success) {
				this.options.onError?.(result.error?.message || 'Le chargement des données a échoué.');
				this.finished = true;
				this.observer.disconnect();
				this.render([]);
				return;
			}
			const payload = result.data ?? {};
			this.totalPages = payload.pages ?? 1;
			this.render(payload.rows ?? payload.data ?? []);
		} finally {
			this.loading = false;
		}

		if (this.page >= this.totalPages) {
			this.finished = true;
			this.observer.disconnect();
		} else {
			this.page++;
		}

	}
	getFilterInput(element) {
		if (!element) return null;
		return element.matches('input, select, textarea') ? element : element.querySelector('input[type="hidden"], select, input');
	}
	render(data) {
		if (!data.length && this.page === 1) {
			const empty = document.createElement('div');
			empty.className = 'table-empty-state';
			empty.textContent = this.options.emptyMessage;
			this.body.insertBefore(empty, this.sentinel);
			return;
		}
		data.forEach(rowData => {
			const row = this.model.cloneNode(true);
			row.style.display = "grid";
			row.classList.remove("model");
			this.applyGridTemplate(row);
			row.dataset.id = eval(`rowData.id`);
			row._data = rowData;
			row.querySelectorAll(".cell").forEach((cell, index) => {
				const col = this.columns[index];
				const formatter = this.options.formatters[col];
				if (formatter) cell.innerHTML = formatter(rowData[col], rowData);
				else cell.textContent = rowData[col] ?? "";
			});
			if( this.options.callbackClick) {
				row.classList.add("select-row");
				row.addEventListener('click',(e)=> {
					this.body.querySelectorAll('.select-row').forEach((r) => {
						r.classList.remove("selected");
					})
					e.currentTarget.classList.add('selected')
      		this.options.callbackClick(row.dataset)
    		})
			}
			// Colonne actions
			const actionsCell = row.querySelector(".actions");
	 
			if (actionsCell) {

			this.options.actions.forEach(action => {
				if (typeof action.visible === "function" && !action.visible(rowData)) {
					return;
				}

				const btn = document.createElement("button");
				btn.className = "btn-action";
				btn.type="button";
				const name = typeof action === 'string' ? action : action.name;
				btn.innerHTML = `<i class="bi ${this.icons[name] ?? action.icon ?? 'bi-three-dots'}"></i>`;
				btn.title = typeof action === 'string' ? action : action.label;
				btn.dataset.action = name;
				actionsCell.appendChild(btn);

			});
		}
		this.body.insertBefore(row, this.sentinel);
		});
	}
	clickRow(numRow) {
		var rows=this.body.querySelectorAll(".flex-row:not(.model)");
		console.log(rows);
	}
	clearRows() {
		this.body.querySelectorAll(".flex-row:not(.model), .table-empty-state")
			.forEach(r => r.remove());
	}
	createSentinel() {
		this.sentinel = document.createElement("div");
		this.sentinel.className = "table-sentinel";
		this.body.appendChild(this.sentinel);
	}
	createObserver() {
		this.observer = new IntersectionObserver(entries => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					this.load();
				}
			});
		}, {
			root: this.body,
			threshold: 1.0
		});

		this.observer.observe(this.sentinel);
	}
	initSorting() {
		this.container.querySelectorAll(".flex-table-header .cell")
			.forEach(cell => {

				const col = cell.dataset.sort;
				if (!col) return;

				cell.style.cursor = "pointer";

				cell.addEventListener("click", () => {

					if (this.sort === col) {
						this.dir = this.dir === "asc" ? "desc" : "asc";
					} else {
						this.sort = col;
						this.dir = "asc";
					}
					this.updateSortIcons();
					this.observer.disconnect();
					this.createObserver();
					this.load(true);
				});

			});
	}
	updateSortIcons() {

		this.container
			.querySelectorAll(".flex-table-header .cell")
			.forEach(cell => {

				const icon = cell.querySelector(".sort-icon");
				if (!icon) return;

				const col = cell.dataset.sort;

				icon.className = "bi sort-icon"; // reset

				if (col === this.sort) {
					icon.classList.add(
						this.dir === "asc" ? "bi-arrow-up" : "bi-arrow-down"
					);
				}

			});

	}
	on(eventName, callback) {
		this.addEventListener(eventName, callback);
	}
	onEvent(eventName, callback) {
		this.container.addEventListener(eventName,callback);
	}
	refreshRow(data) {
		const row = this.getRowById(data.id);
		row.querySelectorAll(".cell[data-field").forEach(
			cell => cell.innerHTML = data[cell.dataset.field]
		)
	}
	getRowById(id) {
		var r=null;
		this.container.querySelectorAll('.flex-row:not(.model)').forEach(
			row => {
				if( row.dataset.id===id ) r = row;
			}
		)
		return r;
	}
}
