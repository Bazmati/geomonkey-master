import './stimulus_bootstrap.js';
import './vendor/bootstrap/css/bootstrap.min.css';
import './vendor/bootstrap/js/bootstrap.bundle.min.js';
import './styles/app.css';
import './styles/app-calendar-cards.css';

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
