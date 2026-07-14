export class Body {
    static create(root) {
        const body = new Body(root);

        return new Proxy(body, {
            get(target, prop, receiver) {
                if (typeof prop !== "string") {
                    return Reflect.get(target, prop, receiver);
                }

                if (prop in target) {
                    return Reflect.get(target, prop, receiver);
                }

                return target.byId(prop);
            }
        });
    }

    constructor(root) {
        this.root = root;
    }

    byId(id) {
        return this.root.querySelector(`#${id}`)
            ?? this.root.querySelector(`#${id.replaceAll("_", "-")}`);
    }

    qs(selector) {
        return this.root.querySelector(selector);
    }

    qsa(selector) {
        return [...this.root.querySelectorAll(selector)];
    }

    name(name) {
        return this.root.querySelector(`[name="${name}"]`);
    }
}
