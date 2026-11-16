import { Controller } from '@hotwired/stimulus';

// Compte à rebours J-x · xh xmin xs, rafraîchi chaque seconde.
// Usage :
//   <span data-controller="countdown"
//         data-countdown-start-value="2026-12-31T20:00:00+00:00"
//         data-countdown-finished-value="C'est maintenant !">…</span>
export default class extends Controller {
    static values = {
        start: String,
        finished: { type: String, default: "C'est maintenant !" },
    };

    connect() {
        this._timer = null;
        this.tick();
    }

    disconnect() {
        if (this._timer !== null) {
            clearTimeout(this._timer);
            this._timer = null;
        }
    }

    pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    tick() {
        const start = new Date(this.startValue).getTime();
        const diff = start - Date.now();

        if (isNaN(start) || diff <= 0) {
            this.element.innerHTML = '<i class="bi bi-lightning-charge me-1"></i> ' + this.finishedValue;
            return;
        }

        const d = Math.floor(diff / 86400000);
        const h = Math.floor(diff / 3600000) % 24;
        const m = Math.floor(diff / 60000) % 60;
        const s = Math.floor(diff / 1000) % 60;

        this.element.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> ' +
            (d > 0 ? 'J-' + d + ' · ' : '') +
            this.pad(h) + 'h ' + this.pad(m) + 'min ' + this.pad(s) + 's';

        this._timer = setTimeout(() => this.tick(), 1000);
    }
}
