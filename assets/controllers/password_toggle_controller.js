import { Controller } from '@hotwired/stimulus';

/*
 * Afficher / masquer un champ mot de passe (bouton œil).
 *
 * Usage (l'input et le bouton doivent être dans le même conteneur) :
 *
 *   <div class="input-group" data-controller="password-toggle">
 *     <input type="password" data-password-toggle-target="input" ...>
 *     <button type="button" data-action="password-toggle#toggle"
 *             aria-label="{{ 'auth.show_password'|trans }}">
 *       <i class="bi bi-eye" data-password-toggle-target="icon"></i>
 *     </button>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'icon'];

    toggle() {
        const show = this.inputTarget.type === 'password';
        this.inputTarget.type = show ? 'text' : 'password';
        this.iconTarget.classList.toggle('bi-eye', !show);
        this.iconTarget.classList.toggle('bi-eye-slash', show);
        // Le focus reste sur le champ : l'input « saute » moins à l'écran.
        this.inputTarget.focus();
    }
}
