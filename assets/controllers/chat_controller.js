import { Controller } from '@hotwired/stimulus';

/*
 * Conversation d'un échange :
 * - affiche toujours le dernier message ;
 * - la zone de saisie s'agrandit avec le texte ;
 * - Entrée envoie le message, Maj+Entrée fait un retour à la ligne.
 */
export default class extends Controller {
    static targets = ['messages', 'field'];

    connect() {
        if (this.hasMessagesTarget) {
            this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
        }
        if (this.hasFieldTarget) {
            this.fieldTarget.focus();
        }
    }

    grow() {
        const field = this.fieldTarget;
        field.style.height = 'auto';
        field.style.height = `${Math.min(field.scrollHeight, 180)}px`;
    }

    submitOnEnter(event) {
        if (event.key === 'Enter' && !event.shiftKey && this.fieldTarget.value.trim() !== '') {
            event.preventDefault();
            this.fieldTarget.form.requestSubmit();
        }
    }
}
