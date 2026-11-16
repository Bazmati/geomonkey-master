import { Controller } from '@hotwired/stimulus';

// Drawer latéral du backoffice (< lg) : ouverture/fermeture, backdrop, touche Échap.
// Usage :
//   <div class="admin-shell" data-controller="admin-sidebar">
//     bouton burger  : data-action="admin-sidebar#toggle"
//     backdrop       : data-admin-sidebar-target="backdrop" data-action="click->admin-sidebar#close"
export default class extends Controller {
    static targets = ['backdrop'];

    connect() {
        this._onKeydown = (event) => {
            if (event.key === 'Escape') { this.close(); }
        };
        window.addEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        window.removeEventListener('keydown', this._onKeydown);
    }

    toggle() {
        this.element.classList.toggle('sidebar-open');
    }

    open() {
        this.element.classList.add('sidebar-open');
    }

    close() {
        this.element.classList.remove('sidebar-open');
    }

    backdropTargetConnected(backdrop) {
        // Accessibilité : le backdrop est un bouton masqué pour les lecteurs d'écran
        backdrop.setAttribute('role', 'presentation');
    }
}
