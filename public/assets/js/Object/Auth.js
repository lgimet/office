
import { Core } from "../Core/Core.js"
export default class Auth extends Core {
    login(email, password, returnTo = '') {
        this.callAction(this.login.name, {
            email: email,
            password: password,
            return_to: returnTo
        },
        {
        callback: (e) => {
            if (e.success) {
                console.log(e.redirect);
            if (e.redirect) {
                window.location = e.redirect;
            }
            } else {
            document.getElementById('login-error').innerText = e.error?.message || 'Erreur de connexion.';
            }
        }});
    }
}
