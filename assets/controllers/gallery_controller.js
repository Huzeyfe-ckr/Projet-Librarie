import { Controller } from '@hotwired/stimulus';

/*
 * Galerie de la page d'une annonce : cliquer une miniature l'affiche en grand.
 *
 * <div data-controller="gallery">
 *   <img data-gallery-target="main">
 *   <button data-action="gallery#show" data-gallery-url-param="…" data-gallery-alt-param="…" data-gallery-target="thumb">
 */
export default class extends Controller {
    static targets = ['main', 'thumb'];

    show({ currentTarget, params }) {
        this.mainTarget.hidden = false; // le contrôleur photo la masquera de nouveau si cette photo ne charge pas
        this.mainTarget.src = params.url;
        this.mainTarget.alt = params.alt;
        this.thumbTargets.forEach((thumb) => thumb.setAttribute('aria-pressed', thumb === currentTarget ? 'true' : 'false'));
    }
}
