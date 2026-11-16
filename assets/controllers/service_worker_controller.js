import { Controller } from '@hotwired/stimulus';

// Enregistrement du service worker PWA, après le chargement complet de la page.
// Usage : <body data-controller="service-worker" data-service-worker-url-value="/sw.js">
export default class extends Controller {
    static values = { url: String };

    connect() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        this._register = () => {
            navigator.serviceWorker.register(this.urlValue).catch(() => {});
        };

        if (document.readyState === 'complete') {
            this._register();
        } else {
            window.addEventListener('load', this._register, { once: true });
        }
    }

    disconnect() {
        if (this._register) {
            window.removeEventListener('load', this._register);
        }
    }
}
