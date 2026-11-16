import { Controller } from '@hotwired/stimulus';

// Copier le lien de l'article dans le presse-papiers.
// Usage :
//   <div data-controller="share-buttons">
//     <button data-action="share-buttons#copy" data-url="…">Copier le lien</button>
export default class extends Controller {
    async copy(event) {
        const btn = event.currentTarget;
        try {
            await navigator.clipboard.writeText(btn.dataset.url);
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Copié !';
            setTimeout(() => { btn.innerHTML = original; }, 2000);
        } catch (e) {
            // Fallback silencieux : rien de critique
        }
    }
}
