import { Controller } from '@hotwired/stimulus';

// Rotateur d'images : deux calques en fondu enchaîné + pastilles.
// Usage :
//   <article data-controller="rotator">
//     <div data-rotator-target="layerA"></div>
//     <div data-rotator-target="layerB"></div>
//     <span data-rotator-target="dots"></span>
//     <script type="application/json" data-rotator-target="slides">[…]</script>
//   </article>
export default class extends Controller {
    static targets = ['layerA', 'layerB', 'dots', 'slides'];

    connect() {
        this.slides = [];
        this.current = 0;
        this.front = 0;
        this.interval = null;

        try {
            const parsed = JSON.parse(this.slidesTarget.textContent);
            parsed.forEach((s) => { if (s.src) { this.slides.push(s.src); } });
        } catch (e) {
            return; // données absentes ou illisibles : pas de rotateur
        }

        if (this.slides.length === 0) {
            this.element.classList.add('blog-card-noimg');
            return;
        }

        this.slides.forEach((src, i) => {
            const d = document.createElement('span');
            d.className = 'gm-rotator-dot';
            d.setAttribute('role', 'img');
            d.setAttribute('aria-label', 'Image ' + (i + 1) + ' sur ' + this.slides.length);
            this.dotsTarget.appendChild(d);
        });

        // Préchargement pour éviter les flashs au changement
        this.slides.forEach((src) => { const im = new Image(); im.src = src; });

        this.layers = [this.layerATarget, this.layerBTarget];
        this.dots = Array.from(this.dotsTarget.children);

        this.layers[0].style.backgroundImage = "url('" + this.slides[0] + "')";
        this.layers[0].classList.add('is-visible');
        this.dots[0].classList.add('is-active');
        this.front = 1;

        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return; // première image statique, pas de défilement
        }

        this.interval = setInterval(() => {
            this.current = (this.current + 1) % this.slides.length;
            this.paint();
        }, 5000);
    }

    disconnect() {
        if (this.interval !== null) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }

    paint() {
        this.layers[this.front].style.backgroundImage = "url('" + this.slides[this.current] + "')";
        this.layers[this.front].classList.add('is-visible');
        this.layers[1 - this.front].classList.remove('is-visible');
        this.dots.forEach((d, i) => d.classList.toggle('is-active', i === this.current));
        this.front = 1 - this.front;
    }
}
