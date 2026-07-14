export class UI {

  // =========================
  // TOAST
  // =========================
  static toast(message, type = "info", options = {}) {

    const duration = options.duration || 3000;

    const icons = {
      ok: "bi-check-circle-fill",
      error: "bi-x-circle-fill",
      warning: "bi-exclamation-triangle-fill",
      info: "bi-info-circle-fill"
    };

    const toast = document.createElement("div");
    toast.className = `toast-alert toast-${type}`;
    toast.innerHTML = `
      <i class="bi ${icons[type] || icons.info}"></i>
      <span>${message}</span>
    `;

    document.body.appendChild(toast);

    // animation entrée
    setTimeout(() => toast.classList.add("show"), 50);

    // disparition auto
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }
  // =========================
  // CONFIRM
  // =========================
  static confirm( options = {}) {
    this.options = options;
    const yesText = options.yesText || "Oui";
    const noText = options.noText || "Non";

    return new Promise(resolve => {

      const icons = {
        ok: "bi-check-circle-fill",
        error: "bi-x-circle-fill",
        warning: "bi-exclamation-triangle-fill",
        info: "bi-info-circle-fill"
      };

      const overlay = document.createElement("div");
      overlay.className = "confirm-overlay";

      const box = document.createElement("div");
      box.className = `confirm-box ${this.options.type}`;

      var strNoText=`<button class="btn btn-no">${noText}</button>`;
      if( this.options.type=="info") {
        strNoText='';
      }
      box.innerHTML = `
        <i class="bi ${icons[this.options.type] || icons.info}"></i>
        <div class="message">${this.options.message}</div>
        <div class="btn-container">
          <button class="btn btn-yes">${yesText}</button>
          ${strNoText}
        </div>
      `;

      overlay.appendChild(box);
      document.body.appendChild(overlay);

      const btnYes = box.querySelector(".btn-yes");
      const btnNo = box.querySelector(".btn-no");

      // Focus automatique sur Oui
      btnYes.focus();

      btnYes.addEventListener("click", () => {
        overlay.remove();
        resolve(true);
      });
if( this.options.type!=="info") {
      btnNo.addEventListener("click", () => {
        overlay.remove();
        resolve(false);
      });
}

      // clic en dehors
      overlay.addEventListener("click", e => {
        if (e.target === overlay) {
          overlay.remove();
          resolve(false);
        }
      });

      // touche clavier
      document.addEventListener("keydown", function escListener(e) {
        if (e.key === "Escape") {
          overlay.remove();
          resolve(false);
          document.removeEventListener("keydown", escListener);
        }
        if (e.key === "Enter") {
          overlay.remove();
          resolve(true);
          document.removeEventListener("keydown", escListener);
        }
      });

    });
  }
}
