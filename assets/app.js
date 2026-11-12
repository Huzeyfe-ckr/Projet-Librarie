/*
 * Point d'entrée JavaScript et CSS de l'application (chargé par {{ importmap('app') }} dans base.html.twig).
 *
 * Tout le comportement des pages est dans des contrôleurs Stimulus (assets/controllers/*_controller.js),
 * branchés dans le HTML avec data-controller="nom". Aucun <script> dans les templates.
 */
import './stimulus_bootstrap.js';
import './styles/app.css';
