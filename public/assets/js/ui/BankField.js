export class BankField {

  constructor(wrapper) {

    this.wrapper = wrapper;

    this.ibanInput = wrapper.querySelector(".bank-iban");
    this.bicInput = wrapper.querySelector(".bank-bic");

    this.ibanHidden = wrapper.querySelector('input[name="iban"]');
    this.bicHidden = wrapper.querySelector('input[name="bic"]');

    this.ibanStatus = wrapper.querySelector(".iban-status");
    this.bicStatus = wrapper.querySelector(".bic-status");

    this.bind();
  }

  bind() {

    this.ibanInput.addEventListener("input", () => this.formatIBAN());
    this.bicInput.addEventListener("input", () => this.formatBIC());

    this.ibanInput.addEventListener("blur", () => this.validateIBAN());
    this.bicInput.addEventListener("blur", () => this.validateBIC());
  }

  /* =========================
     IBAN
  ========================== */

  formatIBAN() {

    let value = this.ibanInput.value
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, "")
      .substring(0, 34);

    value = value.replace(/(.{4})(?=.)/g, "$1 ");

    this.ibanInput.value = value;

    this.ibanHidden.value = value.replace(/\s/g, "");
  }

  validateIBAN() {

    const iban = this.ibanHidden.value;

    if (!iban) return;

    const valid = this.mod97Check(iban);

    this.setStatus(this.ibanStatus, valid);
  }

  mod97Check(iban) {

    const rearranged = iban.slice(4) + iban.slice(0,4);

    const converted = rearranged
      .split("")
      .map(char => {
        const code = char.charCodeAt(0);
        return code >= 65 ? code - 55 : char;
      })
      .join("");

    let remainder = converted;

    while (remainder.length > 2) {
      remainder = (parseInt(remainder.substring(0, 9), 10) % 97)
        + remainder.substring(9);
    }

    return parseInt(remainder, 10) % 97 === 1;
  }

  /* =========================
     BIC
  ========================== */

  formatBIC() {

    let value = this.bicInput.value
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, "")
      .substring(0, 11);

    this.bicInput.value = value;
    this.bicHidden.value = value;
  }

  validateBIC() {

    const bic = this.bicHidden.value;

    if (!bic) return;

    const valid = /^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/.test(bic);

    this.setStatus(this.bicStatus, valid);
  }

  /* =========================
     UI
  ========================== */

  setStatus(el, valid) {

    el.classList.remove("bank-valid", "bank-invalid");

    if (valid) {
      el.textContent = "Valide";
      el.classList.add("bank-valid");
    } else {
      el.textContent = "Invalide";
      el.classList.add("bank-invalid");
    }
  }
}
