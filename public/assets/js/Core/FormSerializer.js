export class FormSerializer {

    static toObject(form) {
        const formData = new FormData(form);
        const result = {};

        for (const [rawKey, value] of formData.entries()) {

            const keys = this.parseKeys(rawKey);
            this.assignDeep(result, keys, this.normalizeValue(form, rawKey, value));
        }

        return result;
    }

    static parseKeys(key) {
        return key
            .replace(/\]/g, '')
            .split('[');
    }

    static assignDeep(obj, keys, value) {
        let current = obj;

        keys.forEach((key, index) => {
            const last = index === keys.length - 1;

            if (last) {
                if (key === '') {
                    // array push
                    current.push(value);
                } else {
                    if (current[key] !== undefined) {
                        if (!Array.isArray(current[key])) {
                            current[key] = [current[key]];
                        }
                        current[key].push(value);
                    } else {
                        current[key] = value;
                    }
                }
            } else {
                if (!current[key]) {
                    current[key] = keys[index + 1] === '' ? [] : {};
                }
                current = current[key];
            }
        });
    }

    static normalizeValue(form, key, value) {
        const field = form.querySelector(`[name="${CSS.escape(key)}"]`);

        if (!field) return value;

        switch (field.type) {
            case 'checkbox':
                return field.checked;

            case 'number':
                return value === '' ? null : Number(value);

            case 'file':
                return field.files.length === 1
                    ? field.files[0]
                    : Array.from(field.files);

            default:
                return value;
        }
    }
}
