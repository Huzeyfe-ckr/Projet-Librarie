import { Controller } from '@hotwired/stimulus';

/*
 * Filtre « Tous / Reçus / Envoyés » de la page Mes échanges.
 *
 * <div data-controller="tabs">
 *   <button data-action="tabs#filter" data-tabs-kind-param="received" data-tabs-target="tab">
 *   <article data-tabs-target="item" data-kind="received">
 */
export default class extends Controller {
    static targets = ['tab', 'item'];

    filter({ currentTarget, params }) {
        this.tabTargets.forEach((tab) => {
            tab.classList.toggle('is-active', tab === currentTarget);
            tab.setAttribute('aria-selected', tab === currentTarget ? 'true' : 'false');
        });
        this.itemTargets.forEach((item) => {
            item.hidden = params.kind !== 'all' && item.dataset.kind !== params.kind;
        });
    }
}
