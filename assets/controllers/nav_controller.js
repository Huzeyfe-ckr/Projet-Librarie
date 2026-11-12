import { Controller } from '@hotwired/stimulus';

/*
 * Menu mobile : ouvre et ferme la navigation.
 *
 * <header data-controller="nav"> … <button data-action="nav#toggle" data-nav-target="toggle">
 */
export default class extends Controller {
    static targets = ['toggle'];

    toggle() {
        const open = this.element.classList.toggle('is-open');
        this.toggleTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // Échap referme le menu
    close(event) {
        if (event.key === 'Escape' && this.element.classList.contains('is-open')) {
            this.toggle();
            this.toggleTarget.focus();
        }
    }
}
