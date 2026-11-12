import { Controller } from '@hotwired/stimulus';

/*
 * Message flash : bouton × pour le fermer, et disparition automatique des messages de succès.
 */
export default class extends Controller {
    static values = { autoHide: Boolean };

    connect() {
        if (this.autoHideValue) {
            this.timer = setTimeout(() => this.close(), 6000);
        }
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    close() {
        this.element.classList.add('is-leaving');
        setTimeout(() => this.element.remove(), 250);
    }
}
