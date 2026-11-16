import './stimulus_bootstrap.js';
import './vendor/bootstrap/css/bootstrap.min.css';
import './vendor/bootstrap/js/bootstrap.bundle.min.js';
import './styles/app.css';
import './styles/app-calendar-cards.css';

// ----- Modales : sortie du contexte d'empilage -----
// Les modales imbriquées dans des conteneurs animés (gmFadeUp en fill-mode
// "both") sont piégées dans un stacking context : le backdrop (z-index 1050)
// se retrouve au-dessus de la modale (z-index 1055). On déplace la modale
// sous <body> juste avant l'affichage, comme recommandé par Bootstrap.
document.addEventListener('show.bs.modal', (event) => {
    const modal = event.target;
    if (modal instanceof HTMLElement && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
});

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
// ----- Dashboard : count-up des cartes stats -----
document.addEventListener('DOMContentLoaded', () => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.stat-value[data-count], .home-stat-value[data-count]').forEach((el) => {
        const target = parseInt(el.dataset.count, 10) || 0;
        if (reduceMotion) { el.textContent = target; return; }
        const duration = 600;
        const start = performance.now();
        const step = (now) => {
            const p = Math.min((now - start) / duration, 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });
});
