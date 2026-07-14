export class ContextMenu {

	constructor(options) {

		this.container = options.container;
		this.menu = this.container.querySelector(options.menu);
		this.targets = this.container.querySelectorAll(options.target);
		this.callbacks = options.callbacks || {};

		this.data = {};

		this.init();

	}

	init() {

		this.targets.forEach(el => {

			el.addEventListener("contextmenu", (e) => {

				e.preventDefault();
				
				const r = /(\d{4})-(\d{1,2})/gm;
				const result = r.exec(el.dataset.week);
				
				
				this.data = {
					date: el.dataset.date,
					week: el.dataset.week,
					hasMenu: el.dataset.hasMenu === "1",
					idMenu: el.dataset.idmenu,
					year: result[1],
					weekNumber: result[2]
				};

				const btnCreate = this.menu.querySelector('button[data-action="create"]');
				const btnEdit = this.menu.querySelector('button[data-action="edit"]');
				const btnDelete = this.menu.querySelector('button[data-action="delete"]');

				if( this.data.hasMenu ) {
					btnCreate.classList.add("hide");
					btnEdit.classList.remove("hide");
					btnDelete.classList.remove("hide");
				}
				else {
					btnCreate.classList.remove("hide");
					btnEdit.classList.add("hide");
					btnDelete.classList.add("hide");
				}
				this.show(e.clientX, e.clientY);

			});

		});
		document.addEventListener("click", () => this.hide());
		this.menu.querySelectorAll("button").forEach(btn => {
			btn.addEventListener("click", () => {
				const action = btn.dataset.action;
				if (this.callbacks[action]) {
                    this.callbacks[action](this.data);
                }
				//this.handleAction(action);
			});
		});

	}

	show(x, y) {

		const menuWidth = this.menu.offsetWidth;
		const menuHeight = this.menu.offsetHeight;

		const windowWidth = window.innerWidth;
		const windowHeight = window.innerHeight;

		if (x + menuWidth > windowWidth) {
			x = windowWidth - menuWidth - 10;
		}

		if (y + menuHeight > windowHeight) {
			y = windowHeight - menuHeight - 10;
		}

		this.menu.style.left = x + "px";
		this.menu.style.top = y + "px";
		this.menu.style.display = "flex";

	}

	hide() {
		this.menu.style.display = "none";
	}

	handleAction(action) {

		console.log("Action:", action);
		console.log("Date:", this.data.date);
		console.log("Week:", this.data.week);

		this.hide();

		switch(action) {

			case "create":
				this.createMenu();
				break;

			case "edit":
				this.editMenu();
				break;

			case "duplicate":
				this.duplicateWeek();
				break;

			case "delete":
				this.deleteMenu();
				break;

		}

	}

	createMenu() {
		alert("Créer menu pour " + this.data.date);
	}

	editMenu() {
		alert("Modifier menu semaine " + this.data.week);
	}

	duplicateWeek() {
		alert("Dupliquer semaine " + this.data.week);
	}

	deleteMenu() {
		alert("Supprimer menu semaine " + this.data.week);
	}

}
