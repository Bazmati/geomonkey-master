import { Controller } from '@hotwired/stimulus';

// Visionneuse plein écran de la galerie : navigation clavier et boutons.
// Usage : <div data-controller="lightbox"> (élément #gm-lightbox)
// Les vignettes .gm-patch[data-full] sont recherchées dans la page.
export default class extends Controller {
    connect() {
        this.patches = Array.from(document.querySelectorAll('.gm-patch[data-full]'));
        this.current = -1;
        this.img = this.element.querySelector('.gm-lightbox-img');
        this.caption = this.element.querySelector('.gm-lightbox-caption');
        this.counter = this.element.querySelector('.gm-lightbox-counter');

        if (this.patches.length === 0) {
            return;
        }

        this._onPatchClick = (index) => (e) => {
            if (e.target.closest('a, form, button')) { return; }
            this.show(index);
        };
        this._patchHandlers = this.patches.map((p, i) => {
            const h = this._onPatchClick(i);
            p.addEventListener('click', h);
            return h;
        });

        this._onClick = (e) => { if (e.target === this.element) { this.close(); } };
        this._onKeydown = (e) => {
            if (this.element.hidden) { return; }
            if (e.key === 'Escape') { this.close(); }
            else if (e.key === 'ArrowLeft') { this.show(this.current - 1); }
            else if (e.key === 'ArrowRight') { this.show(this.current + 1); }
        };

        this.element.querySelector('.gm-lightbox-close').addEventListener('click', this._close = () => this.close());
        this.element.querySelector('.gm-lightbox-prev').addEventListener('click', this._prev = () => this.show(this.current - 1));
        this.element.querySelector('.gm-lightbox-next').addEventListener('click', this._next = () => this.show(this.current + 1));
        this.element.addEventListener('click', this._onClick);
        document.addEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        this.patches.forEach((p, i) => p.removeEventListener('click', this._patchHandlers[i]));
        this.element.removeEventListener('click', this._onClick);
        document.removeEventListener('keydown', this._onKeydown);
    }

    show(index) {
        this.current = (index + this.patches.length) % this.patches.length;
        const p = this.patches[this.current];

        this.img.src = p.dataset.full;
        this.img.alt = p.dataset.title || '';

        let text = '';
        if (p.dataset.title) { text += '<strong>' + p.dataset.title + '</strong>'; }
        if (p.dataset.desc) {
            if (text) { text += '<br>'; }
            text += '<span class="small">' + p.dataset.desc + '</span>';
        }
        this.caption.innerHTML = text;
        this.counter.textContent = (this.current + 1) + ' / ' + this.patches.length;

        this.element.hidden = false;
        this.element.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.element.hidden = true;
        this.element.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
}
