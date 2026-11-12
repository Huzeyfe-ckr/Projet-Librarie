import { Controller } from '@hotwired/stimulus';

/*
 * Demande une confirmation avant d'envoyer un formulaire (refuser, annuler, retirer une annonce…)
 * dans une vraie boîte de dialogue <dialog>, au lieu du window.confirm() du navigateur.
 *
 * <form data-controller="confirm" data-action="confirm#ask"
 *       data-confirm-message-value="Retirer cette annonce ?" data-confirm-button-value="Retirer">
 *
 * La boîte <dialog id="confirm-dialog"> est unique et se trouve dans base.html.twig.
 */
export default class extends Controller {
    static values = { message: String, button: { type: String, default: 'Confirmer' } };

    ask(event) {
        if (this.confirmed) {
            return; // 2e passage après confirmation : on laisse partir le formulaire
        }

        const dialog = document.getElementById('confirm-dialog');
        if (!dialog || typeof dialog.showModal !== 'function') {
            if (!window.confirm(this.messageValue)) event.preventDefault();
            return;
        }

        event.preventDefault();
        dialog.querySelector('[data-confirm-message]').textContent = this.messageValue;
        dialog.querySelector('[data-confirm-ok]').textContent = this.buttonValue;
        dialog.returnValue = '';
        dialog.showModal();

        dialog.addEventListener('close', () => {
            if (dialog.returnValue === 'ok') {
                this.confirmed = true;
                this.element.requestSubmit();
            }
        }, { once: true });
    }
}
