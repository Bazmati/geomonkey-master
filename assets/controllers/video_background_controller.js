import { Controller } from '@hotwired/stimulus';

// Fond vidéo : n'apparaît que si le fichier charge réellement
// (classe is-playing au canplay), et respecte prefers-reduced-motion.
// Usage : <video data-controller="video-background">
export default class extends Controller {
    connect() {
        this._onCanPlay = () => this.element.classList.add('is-playing');
        this.element.addEventListener('canplay', this._onCanPlay, { once: true });

        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.element.pause();
            this.element.removeAttribute('autoplay');
            this.element.classList.remove('is-playing');
        }
    }

    disconnect() {
        this.element.removeEventListener('canplay', this._onCanPlay);
    }
}
