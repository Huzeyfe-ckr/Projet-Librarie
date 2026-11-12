import { Controller } from '@hotwired/stimulus';

/*
 * Formulaire de publication / modification d'une annonce :
 * - ajouter et retirer des livres (CollectionType Symfony, à partir du prototype) ;
 * - désactiver le prix quand l'annonce est un don.
 */
export default class extends Controller {
    static targets = ['list', 'entry', 'addButton', 'type', 'price'];
    static values = { prototype: String, max: Number };

    connect() {
        // Les index déjà rendus par le serveur sont < 1000 : pas de collision possible
        this.nextIndex = 1000;
        this.refresh();
        this.syncPrice();
    }

    add() {
        const holder = document.createElement('div');
        holder.innerHTML = this.prototypeValue.replace(/__name__/g, this.nextIndex++).trim();
        const entry = holder.firstElementChild;
        this.listTarget.appendChild(entry);
        this.refresh();
        entry.querySelector('input')?.focus();
    }

    remove({ currentTarget }) {
        currentTarget.closest('[data-book-form-target="entry"]').remove();
        this.refresh();
    }

    refresh() {
        const entries = this.entryTargets;
        entries.forEach((entry, i) => {
            entry.querySelector('[data-entry-label]').textContent = `Livre ${i + 1}`;
            entry.querySelector('[data-action="book-form#remove"]').hidden = entries.length === 1;
        });
        this.addButtonTarget.disabled = entries.length >= this.maxValue;
    }

    syncPrice() {
        if (!this.hasTypeTarget || !this.hasPriceTarget) return;
        const isDonation = this.typeTarget.value === 'donation';
        this.priceTarget.disabled = isDonation;
        if (isDonation) this.priceTarget.value = '';
        this.priceTarget.closest('.money').classList.toggle('is-disabled', isDonation);
    }
}
