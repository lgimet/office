export class FormValidator {

    constructor(form) {
        this.form = form;
        this.fields = Array.from(form.querySelectorAll('input, textarea, select'));
        this._bindEvents();
    }

    static init(form) {
        if (form._validator instanceof FormValidator) {
            return form._validator;
        }

        const validator = new FormValidator(form);
        form._validator = validator;

        return validator;
    }

    _bindEvents() {
        this.fields.forEach(field => {

            field.addEventListener('blur', () => {
                this.validateField(field);
            });

            field.addEventListener('input', () => {
                if (field.classList.contains('is-invalid')) {
                    this.validateField(field);
                }
            });
        });
    }

    validateField(field) {

        const isValid = field.checkValidity();

        this._removeError(field);

        if (!isValid) {

            this._showError(field);

            field.classList.add('is-invalid');
            field.classList.remove('is-valid');

            this._triggerShake(field);

            return false;
        }

        field.classList.remove('is-invalid');
        field.classList.add('is-valid');

        return true;
    }
    _triggerShake(field) {

        field.classList.remove('shake');

        // Force reflow pour permettre de rejouer l'animation
        void field.offsetWidth;

        field.classList.add('shake');

        field.addEventListener('animationend', () => {
            field.classList.remove('shake');
        }, { once: true });
    }
    _scrollToFirstInvalid() {

        const firstInvalid = this.form.querySelector(':invalid');

        if (!firstInvalid) return;

        const yOffset = -80; // hauteur éventuelle d’un header sticky
        const y = firstInvalid.getBoundingClientRect().top + window.pageYOffset + yOffset;

        window.scrollTo({
            top: y,
            behavior: 'smooth'
        });

        // Focus après le scroll
        setTimeout(() => {
            firstInvalid.focus({ preventScroll: true });
        }, 400);
    }


    validateForm() {
        let valid = true;

        this.fields.forEach(field => {
            if (!this.validateField(field)) {
                valid = false;
            }
        });

        if (!valid) {
            this._scrollToFirstInvalid();
        }

        return valid;
    }

    isValid() {
        return this.validateForm();
    }

   _showError(field) {

        const message = this._getErrorMessage(field);
        const wrapper = field.closest('.form-field');
        if (!wrapper) return;

        let inlineMessage = wrapper.querySelector('.form-error-inline');

        if (!inlineMessage) {
            inlineMessage = document.createElement('div');
            inlineMessage.className = 'form-error-inline';
            wrapper.appendChild(inlineMessage);
        }

        inlineMessage.textContent = message;

        requestAnimationFrame(() => {
            inlineMessage.classList.add('show');
        });
    }


    _removeError(field) {

        const wrapper = field.closest('.form-field');
        if (!wrapper) return;

        const inlineMessage = wrapper.querySelector('.form-error-inline');

        if (inlineMessage) {
            inlineMessage.classList.remove('show');
            setTimeout(() => inlineMessage.remove(), 150);
        }
    }


    _getErrorMessage(field) {

        if (field.validity.valueMissing)
            return 'Ce champ est requis.';

        if (field.validity.typeMismatch)
            return 'Format invalide.';

        if (field.validity.patternMismatch)
            return 'Format incorrect.';

        if (field.validity.tooShort)
            return `Minimum ${field.minLength} caractères.`;

        if (field.validity.tooLong)
            return `Maximum ${field.maxLength} caractères.`;

        return 'Champ invalide.';
    }
}
