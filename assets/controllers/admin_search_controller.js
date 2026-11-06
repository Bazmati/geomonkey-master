// assets/controllers/admin_search_controller.js
// NOTE : bootstrap.bundle.min.js est un bundle UMD, pas ESM — il n'a AUCUN export nommé.
// Son import comme module casserait le chargement de CE contrôleur (Stimulus ne l'enregistre pas).
// Le bundle est déjà chargé en global par app.js : on récupère Modal via window.bootstrap.
import { Controller } from '@hotwired/stimulus';

const bootstrapModal = (element) => {
    const M = window.bootstrap?.Modal ?? window.Bootstrap?.Modal;
    if (!M) {
        console.error('[admin-search] Bootstrap Modal indisponible : bootstrap.bundle.min.js est-il importé dans app.js ?');
        return null;
    }
    return M.getOrCreateInstance(element);
};

export default class extends Controller {
    static targets = ['input', 'results'];
    static values = { url: String };

    connect() {
        this.timer = null;
        this.abort = null;
        this.activeIndex = -1;
        this.items = [];

        this.boundKeydown = (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                this.open();
            }
        };
        document.addEventListener('keydown', this.boundKeydown);

        this.element.addEventListener('shown.bs.modal', () => {
            this.inputTarget.focus();
            this.inputTarget.select();
        });
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundKeydown);
        clearTimeout(this.timer);
    }

    open() {
        bootstrapModal(this.element)?.show();
    }

    search() {
        const q = this.inputTarget.value.trim();
        clearTimeout(this.timer);

        if (q.length < 2) {
            this._hint('Tapez au moins 2 caractères');
            return;
        }

        this.timer = setTimeout(() => this._fetch(q), 250);
    }

    async _fetch(q) {
        if (this.abort) {
            this.abort.abort();
        }
        this.abort = new AbortController();
        this._hint('Recherche…');

        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(q)}`, {
                signal: this.abort.signal,
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            this._render(data.groups || []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this._hint('Erreur de recherche, réessayez.');
            }
        }
    }

    _render(groups) {
        this.activeIndex = -1;

        if (groups.length === 0) {
            this._hint('Aucun résultat');
            return;
        }

        const fragment = document.createDocumentFragment();

        for (const group of groups) {
            const header = document.createElement('div');
            header.className = 'gm-search-group';
            header.textContent = group.label;
            fragment.appendChild(header);

            for (const item of group.items) {
                const link = document.createElement('a');
                link.href = item.url;
                link.className = 'gm-search-item';

                const title = document.createElement('span');
                title.textContent = item.title;

                const subtitle = document.createElement('small');
                subtitle.className = 'text-gm-muted';
                subtitle.textContent = item.subtitle || '';

                const row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-center gap-3 w-100';
                row.appendChild(title);
                row.appendChild(subtitle);

                link.appendChild(row);
                fragment.appendChild(link);
            }
        }

        this.resultsTarget.replaceChildren(fragment);
        this.items = [...this.resultsTarget.querySelectorAll('.gm-search-item')];
        if (this.items.length > 0) {
            this._setActive(0);
        }
    }

    _setActive(index) {
        this.items.forEach((item, i) => item.classList.toggle('active', i === index));
        this.activeIndex = index;
    }

    down(event) {
        event.preventDefault();
        if (this.items.length) {
            this._setActive(Math.min(this.activeIndex + 1, this.items.length - 1));
        }
    }

    up(event) {
        event.preventDefault();
        if (this.items.length) {
            this._setActive(Math.max(this.activeIndex - 1, 0));
        }
    }

    go(event) {
        if (this.items.length === 0) {
            return;
        }
        event.preventDefault();
        const item = this.items[Math.max(this.activeIndex, 0)];
        bootstrapModal(this.element)?.hide();
        window.location.assign(item.getAttribute('href'));
    }

    _hint(text) {
        const p = document.createElement('p');
        p.className = 'text-gm-muted px-3 py-2 mb-0';
        p.textContent = text;
        this.resultsTarget.replaceChildren(p);
    }
}