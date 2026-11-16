import { Controller } from '@hotwired/stimulus';

// Ombre + opacité renforcée dès qu'on quitte le haut de page.
// Usage : <nav data-controller="navbar-scroll">
export default class extends Controller {
    connect() {
        this._onScroll = () => {
            this.element.classList.toggle('gm-nav-scrolled', window.scrollY > 10);
        };
        window.addEventListener('scroll', this._onScroll, { passive: true });
        this._onScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this._onScroll);
    }
}
