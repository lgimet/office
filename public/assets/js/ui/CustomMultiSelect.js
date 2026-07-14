export class CustomMultiSelect {

  constructor(el) {
    this.el = el;
    this.hidden = el.querySelector("input[type=hidden]");
    this.label = el.querySelector(".cms-label");
    this.dropdown = el.querySelector(".cms-dropdown");

    this.url = el.dataset.url;
    this.placeholder = el.dataset.placeholder;
    this.values = JSON.parse(el.dataset.initial || "[]").map(v => String(v));;
    this.options = [];
    this.activeIndex = -1;

    this.init();
  }

  async init() {
    await this.loadOptions();
    this.renderOptions();
    this.renderLabel();
    this.bindEvents();
  }

  async loadOptions() {
    if (!this.url) return;

    const res = await fetch(this.url);
    const r = await res.json(); 
    this.options = r.data.map(o => ({
            value: String(o.id),
            label: o.label
        }));
    // attendu : [{value:"1", label:"Jean"}, ...]
  }

 renderOptions() {
  this.dropdown.innerHTML = "";

  const available = this.options
    .filter(opt => !this.values.includes(opt.value));

  available.forEach((opt, index) => {

    const item = document.createElement("div");
    item.className = "cms-option";
    item.dataset.value = opt.value;
    item.textContent = opt.label;

    if (index === this.activeIndex) {
      item.classList.add("active");
    }

    this.dropdown.appendChild(item);
  });

  if (!available.length) {
    this.dropdown.innerHTML = `<div class="cms-empty">Plus d'éléments</div>`;
  }
}



renderLabel() {
    if (!this.values.length) {
        this.label.innerHTML = this.placeholder;
        this.hidden.value = "[]";
        return;
    }

    const selected = this.options.filter(o =>
        this.values.includes(o.value)
    );

    this.label.innerHTML = selected.map(o =>
        `<span class="cms-badge" data-value="${o.value}">
        ${o.label}
        <span class="cms-remove">&times;</span>
        </span>`
    ).join("");
    this.hidden.value = JSON.stringify(this.values);
}


  bindEvents() {

    this.label.addEventListener("click", () => {
      this.dropdown.classList.toggle("open");
    });

    this.dropdown.addEventListener("click", e => {
      const option = e.target.closest(".cms-option");
      if (!option) return;
      const value = option.dataset.value;
      if (this.values.includes(value)) {
        this.values = this.values.filter(v => v !== value);
      } else {
        this.values.push(value);
      }
      this.renderOptions();
      this.renderLabel();
    });

    document.addEventListener("click", e => {
      if (!this.el.contains(e.target)) {
        this.dropdown.classList.remove("open");
      }
    });
    this.label.addEventListener("click", e => {
        const badge = e.target.closest(".cms-badge");
        if (badge) {

            const value = String(badge.dataset.value);

            this.values = this.values.filter(v => v !== value);

            this.renderOptions();
            this.renderLabel();

            return;
        }

        //this.dropdown.classList.toggle("open");
    });
    this.label.addEventListener("keydown", (e) => {
        const available = this.options
            .filter(opt => !this.values.includes(opt.value));

        if (!available.length) return;

        switch (e.key) {

            case "ArrowDown":
            e.preventDefault();
            this.dropdown.classList.add("open");
            this.activeIndex = (this.activeIndex + 1) % available.length;
            this.renderOptions();
            break;

            case "ArrowUp":
            e.preventDefault();
            this.dropdown.classList.add("open");
            this.activeIndex =
                (this.activeIndex - 1 + available.length) % available.length;
            this.renderOptions();
            break;

            case "Enter":
            e.preventDefault();
            if (this.activeIndex >= 0) {
                const value = available[this.activeIndex].value;
                this.values.push(value);
                this.activeIndex = -1;
                this.renderOptions();
                this.renderLabel();
            }
            break;

            case "Backspace":
            if (!this.label.textContent.trim() && this.values.length) {
                this.values.pop();
                this.renderOptions();
                this.renderLabel();
            }
            break;

            case "Escape":
            this.dropdown.classList.remove("open");
            this.activeIndex = -1;
            break;
        }
    });
  }
}
