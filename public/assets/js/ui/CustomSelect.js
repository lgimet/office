export class CustomSelect extends EventTarget {
    
  constructor({
    element,
    url,
    mode = "search", // "search" | "list"
    placeholder = ""
  }) {
    super();

    this.container = element;
    this.url = url;
    this.mode = mode;
    this.placeholder = placeholder;
    this.data = [];
    this.activeIndex = -1;


    this.build();
    this.bindEvents();

    const initialValue = this.hidden.value;

    if (this.mode === "list") {
      this.loadList(initialValue);
    } else if (initialValue) {
      this.loadInitialValue(initialValue);
    }
  }
  async loadList(initialValue = null) {
    try {
      const response = await fetch(this.url);
      const r = await response.json();
      this.data = r.data;
      this.renderOptions(this.data);
      if (initialValue) {
        const found = this.data.find(item => item.id == initialValue);
        if (found) this.select(found, false);
      }

    } catch (err) {
      console.error("List load error", err);
    }
  }

  build() {
  this.container.classList.add("custom-select");
  this.hidden = this.container.querySelector("input[type='hidden']");
  this.isRequired = this.hidden.hasAttribute("required");
  this.isDisabled = this.hidden.hasAttribute("disabled");

  


  if (!this.hidden) {
    console.warn("CustomSelect: hidden input missing");
    return;
  }

  // créer id unique si absent
  this.inputId = this.container.id + "_input";

  this.wrapper = document.createElement("div");
  this.wrapper.className = "custom-select-input-wrapper";

  this.input = document.createElement("input");
  this.input.type = "text";
  this.input.id = this.inputId; // IMPORTANT
  this.input.placeholder = this.placeholder;
  this.input.autocomplete = "off";


  this.wrapper.appendChild(this.input);

  if (this.isRequired) {

    this.validator = document.createElement("input");
    this.validator.type = "text";
    this.validator.required = true;
    this.validator.tabIndex = -1;

    this.validator.className = "custom-select-validator";
    this.validator.addEventListener("invalid", () => {
      this.input.classList.add("is-invalid");
    });

    this.validator.addEventListener("input", () => {
      this.input.classList.remove("is-invalid");
    });

    this.wrapper.appendChild(this.validator);
  }
  if (this.isDisabled) {
    this.disable();
  }
  if (this.mode === "list") {
    this.input.readOnly = true;

    this.chevron = document.createElement("i");
    this.chevron.className = "bi bi-chevron-down custom-select-chevron";
    this.wrapper.appendChild(this.chevron);
  }

  this.dropdown = document.createElement("div");
  this.dropdown.className = "custom-select-dropdown";

  this.container.appendChild(this.wrapper);
  this.container.appendChild(this.dropdown);

  const label = document.querySelector(`label[for='${this.container.id}']`);
  if (label) {
    label.setAttribute("for", this.inputId);
  }
  }
  disable() {
    this.isDisabled = true;

    this.container.classList.add("disabled");
    this.input.disabled = true;

    if (this.validator) {
      this.validator.disabled = true;
    }
  }
  enable() {
    this.isDisabled = false;

    this.container.classList.remove("disabled");
    this.input.disabled = false;

    if (this.validator) {
      this.validator.disabled = false;
    }
  }
  async loadInitialValue(id) {
    try {
      const response = await fetch(`${this.url}`,
        {
          method: 'POST',
          credentials: 'same-origin', // important !
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            method: "get",
            id: id
          })
        });
      const item = await response.json();
      this.select(item.data[0], false);

    } catch (err) {
      console.error("Initial value load error", err);
    }
  }
  filterLocal(query) {
    const filtered = this.data.filter(item =>
      item.label.toLowerCase().includes(query.toLowerCase())
    );

    this.renderOptions(filtered);
  }
  highlight(items) {

    items.forEach(item =>
      item.classList.remove("active")
    );

    const activeItem = items[this.activeIndex];

    if (activeItem) {
      activeItem.classList.add("active");

      // scroll auto si nécessaire
      activeItem.scrollIntoView({
        block: "nearest"
      });
    }
  }
  bindEvents() {
    if (this.isDisabled) return;
    this.input.addEventListener("keydown", (e) => {
        const items = this.dropdown.querySelectorAll(".custom-select-option");
        if (!items.length) return;
        switch (e.key) {

          case "ArrowDown":
            e.preventDefault();
            this.activeIndex++;
            if (this.activeIndex >= items.length) {
              this.activeIndex = 0;
            }
            this.highlight(items);
            break;

          case "ArrowUp":
            e.preventDefault();
            this.activeIndex--;
            if (this.activeIndex < 0) {
              this.activeIndex = items.length - 1;
            }
            this.highlight(items);
            break;

          case "Enter":
            e.preventDefault();
            if (this.activeIndex >= 0) {
              items[this.activeIndex].click();
            }
            break;

          case "Escape":
            this.close();
            break;
        }

    });

    if( this.mode === "list") {
      this.input.addEventListener("click", () => {
        this.renderOptions(this.data);
        this.open();
      });
    }
    else {
      this.input.addEventListener("focus", () => this.open());
      this.input.addEventListener("input", (e) => {
        this.fetchData(e.target.value);
      });
    }
    document.addEventListener("click", (e) => {
      if (!this.container.contains(e.target)) {
        this.close();
      }
    });

    this.dropdown.addEventListener("click", (e) => {
        const option = e.target.closest(".custom-select-option");
        if (!option) return;
        const id = option.dataset.id;
        const label = option.textContent;
        this.select({ id, label });
      });
  }
  async fetchData(query = "") {
    try {
      const response = await fetch(`${this.url}?q=${encodeURIComponent(query)}`);
      const data = await response.json();

      this.data = data.data;
      this.renderOptions();

    } catch (err) {
      console.error("Select fetch error", err);
    }
  }
  renderOptions(data = this.data) {
    this.dropdown.innerHTML = "";

    if (!data.length) {
      this.dropdown.innerHTML = `<div class="custom-select-empty">Aucun résultat</div>`;
      return;
    }

    data.forEach(item => {
      const div = document.createElement("div");
      div.className = "custom-select-option";
      div.dataset.id = item.id;
      div.textContent = item.label;
      this.dropdown.appendChild(div);
    });
  }
  select(item, trigger = true) {
    this.selected = item;

    this.input.value = item.label;
    this.hidden.value = item.id;

    this.close();
    if (this.validator) {
      this.validator.value = item.id; // important !
    }
    if (trigger) {
      this.dispatchEvent(
        new CustomEvent("change", {
          detail: item
        })
      );
    }
  }
  open() {
    if (this.isDisabled) return;
    if (this.selected) {
      const items = this.dropdown.querySelectorAll(".custom-select-option");
      this.activeIndex = Array.from(items).findIndex(
        item => item.dataset.id == this.selected.id
      );
      this.highlight(items);
    }

    if (this.chevron) {
      this.chevron.classList.add("open");
    }
    this.dropdown.classList.add("active");
    this.activeIndex = -1;
  }
  close() {
    this.dropdown.classList.remove("active");

    if (this.chevron) {
      this.chevron.classList.remove("open");
    this.activeIndex = -1;
  }
  }
}
