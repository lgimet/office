import { Core } from "./Core.js";
import { initControls } from "./ControlInitializer.js";


const version = Date.now();

document.addEventListener("DOMContentLoaded", () => {
    initControls(document);
});

document.querySelectorAll(".nav-item").forEach(element => {
    element.addEventListener('click',(e)=> {
        const route = e.currentTarget.dataset.action;
        const parts = route.split('.');
        const object = parts[0];
        const action = parts.length === 2 ? parts[1] : parts[2];
        const subobject = parts.length === 3 ? parts[1] : null;

        
        import(`../Object/${object}.js`).then(module => {
            const Instance = new module.default();
            if( subobject ) {
                return Instance[Core.lcfirst(subobject)]?.[action]?.();
            }
            return Instance[action]?.();
        });
    });
})
