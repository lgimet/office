import { appState } from './AppState.js';
import { Body } from "./Body.js";
import { initControls } from "./ControlInitializer.js";
import { UI } from "../ui/UI.js";

export class Core extends EventTarget {



	static EXPIRED=1;	
	static INVALID=2;

	actions = [];
	idName = null;
	basePath = null;
	
	constructor(basePath=null,children = {}) {
		super();
		this.basePath = basePath;
		for (const [key, resolver] of Object.entries(children)) {

            Object.defineProperty(this, key, {
				configurable : true,
                get: () => {
                    const ChildClass = resolver();
                    const instance = new ChildClass(
                        `${this.basePath}/${ChildClass.name}`
                    );
                    Object.defineProperty(this, key, {
                        value: instance,
						configurable: false,
            			writable: false
                    });

                    return instance;
                }
            });
		}
		this.createdAt = new Date();
		this.updatedAt = new Date();
		this.tabsContainer = document.getElementById('tabsBar');
		this.windowsContainer = document.getElementById('windowsContainer');
	}
	get objectName() {
		return this.constructor.name; 
	}
	getActions() {
		return Array.isArray(this.actions) ? [...this.actions] : [];
	}
	get window() {
		return this.getActiveWindowRecord()?.window ?? null;
	}
	get bodyRef() {
		return this.getActiveWindowRecord()?.bodyRef ?? null;
	}
	async  loadWindowCss(module) { 
		if (appState.loadedCss.has(module)) return;

		try {
			const response = await fetch(`/assets/css/objects${module}.css`, {
				method: 'HEAD'
			});
			
			if (response.ok===false) return;

			const link = document.createElement('link');
			link.rel = 'stylesheet';
			link.href = `/assets/css/objects/${module}.css`;

			document.head.appendChild(link);
			appState.loadedCss.add(module);

		} catch (e) {
			console.warn('CSS non trouvé pour', module); 
		}
	}
	openWindow(params) {
		const P = {
			title: null,
			contentHtml: null,
			object: null,
			subobject:null,
			action: null,
			id: null,
			...params
		};
		const windowId = this.buildWindowId(P.object, P.subobject, P.action, P.id);
		const existingWindow = appState.openWindows.get(windowId);
		if (existingWindow) {
			this.activateWindow(windowId);
			return existingWindow;
		}

		const fCss = `/${[P.object, P.subobject, P.action].filter(Boolean).join('/')}`;
		this.loadWindowCss(fCss);

    // === Création de l'onglet ===
    const tab = document.createElement('div');
    tab.className = 'tab';
    tab.dataset.window = windowId;
    tab.innerHTML = `
        <span>${P.title}</span>
        <span class="tab-dirty-indicator"></span>
        <span class="tab-close">✕</span>
    `;
    this.tabsContainer.appendChild(tab);
    setTimeout(() => this.scrollTabIntoView(tab), 50);

    // === Création de la fenêtre ===
    const windowDiv = document.createElement('div');
    windowDiv.className = 'window';
    windowDiv.id = windowId;
	const windowBody = document.createElement('div');
    windowBody.className = `window-body ${[P.object, P.subobject, P.action].filter(Boolean).join('-')}`;
    windowBody.innerHTML = `<h2>${P.action}</h2><p>Chargement...</p>`;
    windowDiv.appendChild(windowBody);
    this.windowsContainer.appendChild(windowDiv);
	const actions = this.getActions();

    const record = {
		id: windowId,
		tab,
		window: windowDiv,
		body: windowBody,
		bodyRef: Body.create(windowBody),
		footer: null,
		footerActions: null,
		actions,
		object: P.object,
		subobject: P.subobject,
		action: P.action,
		resourceId: P.id,
		dirtyTracker: null
	};

    appState.openWindows.set(windowId, record);

	this.renderWindowFooter(record);

    // === Click tab ===
    tab.addEventListener('click', e => {
        if (e.target.classList.contains('tab-close')) return this.closeWindow(windowId);
        this.activateWindow(windowId);
    });

    this.activateWindow(windowId);
    this.loadWindowContent(record, P.contentHtml);
	return record;
}

	activateWindow(windowId) {

		appState.openWindows.forEach((w) => {
			w.tab.classList.remove('active');
			w.window.classList.remove('active');
		});

		const current = appState.openWindows.get(windowId);
		if (!current) return;
		current.tab.classList.add('active');
		current.window.classList.add('active');
		appState.activeWindowId = windowId;

		this.scrollTabIntoView(current.tab);
}
	scrollTabIntoView(tab) {
		const tabsBar = document.getElementById('tabsBar');

		const tabRect = tab.getBoundingClientRect();
		const containerRect = tabsBar.getBoundingClientRect();

		if (tabRect.right > containerRect.right) {
			tabsBar.scrollLeft += tabRect.right - containerRect.right + 20;
		}

		if (tabRect.left < containerRect.left) {
			tabsBar.scrollLeft -= containerRect.left - tabRect.left + 20;
		}
	}
	closeWindow(windowId) {

		const w = appState.openWindows.get(windowId);
		if (!w) return;

		w.tab.remove();
		w.window.remove();

		appState.openWindows.delete(windowId);
		if (appState.activeWindowId === windowId) {
			appState.activeWindowId = null;
		}

		// Activer la dernière ouverte
		const remaining = [...appState.openWindows.keys()];
		if (remaining.length > 0) {
			this.activateWindow(remaining[remaining.length - 1]);
		}
	}
	loadWindowContent(windowRecord, contentHtml) {

		windowRecord.body.innerHTML = contentHtml;
		windowRecord.bodyRef = Body.create(windowRecord.body);
		windowRecord.dirtyTracker = initControls(windowRecord.body, {
			tab: windowRecord.tab
		});
		this.bindWindowForms(windowRecord);

	}
	renderWindowFooter(windowRecord) {
		if (windowRecord.footer) {
			windowRecord.footer.remove();
			windowRecord.footer = null;
			windowRecord.footerActions = null;
		}

		if (!windowRecord.actions.length) return;

		const footer = document.createElement('div');
		footer.className = 'window-footer';

		const footerLeft = document.createElement('div');
		footerLeft.className = 'footer-left';
		footer.appendChild(footerLeft);

		const footerActions = document.createElement('div');
		footerActions.className = 'footer-actions';
		footer.appendChild(footerActions);

		windowRecord.actions.forEach((action) => {
			const btn = document.createElement('button');
			btn.type = action.type ?? 'button';
			btn.className = action.className ?? 'm-btn m-btn--primary';
			if (action.icon) {
				btn.innerHTML = `<i class="bi ${action.icon}"></i><span>${action.label ?? ''}</span>`;
			} else {
				btn.textContent = action.label ?? '';
			}
			if (action.disabled) {
				btn.disabled = true;
			}

			btn.addEventListener('click', (event) => {
				action.callback?.(windowRecord, this, event);
			});
			footerActions.appendChild(btn);
		});

		windowRecord.footer = footer;
		windowRecord.footerActions = footerActions;
		windowRecord.window.appendChild(footer);
	}
	bindWindowForms(windowRecord) {
		windowRecord.body.querySelectorAll('form').forEach((form) => {
			form.addEventListener('submit', (event) => {
				event.preventDefault();
			});
		});
	}
	collectFormData(form) {
		const formData = new FormData(form);
		const values = {};

		for (const [key, value] of formData.entries()) {
			if (Object.hasOwn(values, key)) {
				if (!Array.isArray(values[key])) {
					values[key] = [values[key]];
				}
				values[key].push(value);
				continue;
			}
			values[key] = value;
		}

		form.querySelectorAll('input[type="checkbox"][name]').forEach((input) => {
			values[input.name] = input.checked ? 1 : 0;
		});

		return values;
	}
	handleJsonResponse(responseJson, requestOptions = {}) {
		if (responseJson.success) {
			const object = responseJson.object || 'Unknown';
			const subobject = responseJson.subobject || null;
			const action = responseJson.action || 'Unknown';
			const title = responseJson.title || '';
			window.CSRF_TOKEN = responseJson.csrf || window.CSRF_TOKEN || null;

			var contentHtml=null;
			if( responseJson.html ) {
				contentHtml = responseJson.html || `<pre>${JSON.stringify(responseJson.data, null, 2)}</pre>`;
				if(requestOptions.refresh === false) {
					
					this.openWindow({
						id: responseJson.id || null,
						action: action,
						title: title,
						contentHtml: contentHtml,
							object: object,
							subobject: subobject,
						})
				} else {
					const targetWindow = this.resolveWindowRecord(requestOptions.windowId);
					if (targetWindow) {
						this.loadWindowContent(targetWindow, contentHtml);
					}
				}
			}
		} else {
			switch(responseJson.error.code) {
				case 401:
					switch(responseJson.error.underCode)
					{
						case Core.EXPIRED:
							UI.confirm({
								title: "Session",
								message: "Votre session a expiré.<br>Veuillez vous reconnecter.",
								type: "info",
								yesText: "Ok",
								noText: "noText"
							}).then(result => {
								window.location.href = '/';
							})
							break;
						case Core.INVALID:
							UI.confirm({
								title: "Session",
								message: "Token invalide.<br>Veuillez vous reconnecter.",
								type: "info",
								yesText: "Ok",
								noText: "noText"
							}).then(result => {
								window.location.href = '/';
							})
							break;
					}
					break;
			}
			
		}
	}
	info() {
		return {
			id: this.id,
			name: this.name,
			objectName: this.objectName,
			createdAt: this.createdAt,
			updatedAt: this.updatedAt
		};
	}
	async callAction(action, params = {}, options = {}) {
		const requestOptions = {
			callback: null,
			subContainer: null,
			refresh: false,
			windowId: null,
			...options
		};
		const url = this.basePath
						? `/${this.basePath}/${action}`
						: `/${this.objectName}/${action}`;

		// Ne pas faire appel si la fenêtre existe déjà
		if (!requestOptions.refresh) {
			let windowId = this.#getWindowId(url,params?.[this.idName]);
			if (appState.openWindows.has(windowId)) {
				this.activateWindow(windowId);
				return;
			}
		} else if (!requestOptions.windowId) {
			requestOptions.windowId = appState.activeWindowId;
		}
		params.csrf = window.CSRF_TOKEN || null;
		
		try {
			const res = await fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(params)
			});

			const json = await res.json();

			this.handleJsonResponse(json, requestOptions);
			requestOptions.callback?.(json);

		} catch (err) {
			console.log(err);
		}
	}
	#getWindowId(route,id) {
		let parts = route.split('/');
		parts.shift();
		let object = parts[0];
		let subobject = parts.length > 2 ? parts[1] : null;
		let action = parts.length > 2 ? parts[2] : parts[1];
		
		return `${[object,subobject,action,id].filter(Boolean).join('_')}`;
	}
	buildWindowId(object, subobject, action, id = null) {
		return `${[object, subobject, action, id].filter(Boolean).join('_')}`;
	}
	resolveWindowRecord(windowId = null) {
		const targetId = windowId ?? appState.activeWindowId;
		if (!targetId) return null;
		return appState.openWindows.get(targetId) ?? null;
	}
	getActiveWindowRecord() {
		return this.resolveWindowRecord();
	}
	trigger(objectName,eventName,data) {
		const target = document.getElementById(objectName);
		if (!target) return;

		target.dispatchEvent(
			new CustomEvent(eventName,{detail : data})
		)
	}
	//-- Fonction static
	static ucfirst(str) {
		return str.charAt(0).toUpperCase() + str.slice(1);
	}
	static lcfirst(str) {
    return str.charAt(0).toLowerCase() + str.slice(1);
}

}
