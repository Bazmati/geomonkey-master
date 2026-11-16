import { Controller } from '@hotwired/stimulus';

// Gestion de la collection d'images du formulaire d'article (admin) :
// ajout dynamique depuis le prototype Symfony + boutons « Retirer ».
// Usage : <div id="blog_post_images" data-controller="blog-images-collection">
// (le bouton #add-blog-image est lié au connect)
export default class extends Controller {
    connect() {
        this._index = parseInt(this.element.dataset.index || '0', 10);
        this._prototype = this.element.dataset.prototype;

        this._onAdd = () => this.add();
        const addBtn = document.getElementById('add-blog-image');
        if (addBtn) {
            addBtn.addEventListener('click', this._onAdd);
        }

        this._onRemoveClick = (e) => {
            const btn = e.target.closest('.blog-image-remove');
            if (btn) { btn.closest('.blog-image-item').remove(); }
        };
        this.element.addEventListener('click', this._onRemoveClick);
    }

    disconnect() {
        const addBtn = document.getElementById('add-blog-image');
        if (addBtn) {
            addBtn.removeEventListener('click', this._onAdd);
        }
        this.element.removeEventListener('click', this._onRemoveClick);
        this.element.dataset.index = this._index;
    }

    add() {
        // __name__ est le placeholder de Symfony dans le prototype :
        // on le remplace par un numéro unique à chaque clic.
        const html = this._prototype.replace(/__name__/g, this._index++);
        const div = document.createElement('div');
        div.className = 'border rounded p-3 mb-2 blog-image-item';
        div.innerHTML = html
            + '<button type="button" class="btn btn-outline-danger btn-sm mt-2 blog-image-remove">'
            + '<i class="bi bi-trash"></i> Retirer</button>';

        this.element.appendChild(div);
        div.querySelectorAll('select, input[type="number"], input[type="text"]')
            .forEach((el) => el.classList.add('form-control', 'form-select', 'mb-1'));
    }
}
