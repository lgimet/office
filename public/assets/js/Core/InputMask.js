export class InputMask {

  constructor(input) {
    this.input = input;
    this.type = input.dataset.mask;

    this.bind();
  }

  bind() {
    this.input.addEventListener("input", () => {
      this.format();
    });

    this.input.addEventListener("keydown", (e) => {
      if (e.key === "Backspace") return;
    });
  }

  format() {
    let value = this.input.value.replace(/\D/g, "");

    switch (this.type) {

      case "phone":
        value = value.substring(0, 10);
        value = value.replace(/(\d{2})(?=\d)/g, "$1 ");
        break;

      case "date":
        value = value.substring(0, 8);
        if (value.length > 4)
          value = value.replace(/(\d{2})(\d{2})(\d+)/, "$1/$2/$3");
        else if (value.length > 2)
          value = value.replace(/(\d{2})(\d+)/, "$1/$2");
        break;

      case "card":
        value = value.substring(0, 16);
        value = value.replace(/(\d{4})(?=\d)/g, "$1 ");
        break;

      case "number":
        break;
    }

    this.input.value = value;
  }
}
