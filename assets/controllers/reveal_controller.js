import { Controller } from '@hotwired/stimulus';

/*
 * Apparition douce des éléments .reveal quand ils entrent dans l'écran.
 * Le décalage (--d) est calculé d'après la position de l'élément parmi ses frères : effet « en cascade ».
 * Désactivé si l'utilisateur a demandé moins d'animations (prefers-reduced-motion).
 *
 * <body data-controller="reveal">
 */
export default class extends Controller {
    connect() {
        const items = this.element.querySelectorAll('.reveal');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion || !('IntersectionObserver' in window)) {
            items.forEach((el) => el.classList.add('is-visible'));
            return;
        }

        items.forEach((el) => {
            const index = Array.prototype.indexOf.call(el.parentElement.children, el);
            el.style.setProperty('--d', `${(index % 6) * 60}ms`);
        });

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    this.observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        items.forEach((el) => this.observer.observe(el));
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
