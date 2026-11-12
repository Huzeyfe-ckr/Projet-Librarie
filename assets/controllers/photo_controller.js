import { Controller } from '@hotwired/stimulus';

/*
 * Photo qui ne charge pas (fichier supprimé, hébergeur en panne…) : on la masque.
 * Pour une photo principale, la couverture générée dessinée dessous reste alors visible ;
 * pour une miniature, on masque l'élément indiqué par data-photo-hide-value (ex. "li").
 *
 * <img data-controller="photo" data-action="error->photo#fail">
 */
export default class extends Controller {
    static values = { hide: String };

    connect() {
        // L'image a pu échouer avant que Stimulus ne démarre
        if (this.element.complete && this.element.naturalWidth === 0 && this.element.getAttribute('src')) {
            this.fail();
        }
    }

    fail() {
        const target = this.hasHideValue ? this.element.closest(this.hideValue) : this.element;
        if (target) target.hidden = true;
    }
}
