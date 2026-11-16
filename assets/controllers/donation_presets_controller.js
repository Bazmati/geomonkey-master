import { Controller } from '@hotwired/stimulus';

// Montants suggérés du don : remplissent le champ du formulaire.
// Usage :
//   <div data-controller="donation-presets">
//     <button data-action="donation-presets#select" data-amount="10">10€</button>
//     … <input class="donation-amount-input">
export default class extends Controller {
    select(event) {
        const btn = event.currentTarget;
        const input = this.element.querySelector('.donation-amount-input');
        if (input) {
            input.value = btn.dataset.amount;
        }
        this.element.querySelectorAll('.donation-preset').forEach((b) => {
            b.classList.toggle('active', b === btn);
        });
    }
}
