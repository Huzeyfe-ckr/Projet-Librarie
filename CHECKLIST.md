# Checklist BookShare

Revue du code au 12/11/2026 (entités, contrôleurs, formulaires, repositories, templates, CI).
Coche au fur et à mesure. L'ordre conseillé est en bas du fichier.

Cases : `[x]` fait · `[~]` en partie · `[ ]` à faire

Légende : 🔴 bug / sécurité · 🟠 qualité du code · 🟢 nouvelle fonctionnalité · 🎨 interface · 🧪 tests et outillage

---

## 🔴 1. À corriger en priorité

### Échanges
- [ ] **Le cycle de vie d'un échange n'existe pas.** `ExchangeStatus` a 5 valeurs, mais aucune route ne permet d'accepter, refuser, terminer ou annuler une demande. Un échange reste donc `pending` pour toujours et l'annonce ne passe jamais en `reserved` ni en `completed`. → voir l'itération 1.
- [x] **On peut encore écrire dans un échange refusé ou annulé.** Dans `ExchangeController::show()`, bloquer l'envoi de messages si le statut est `refused`, `cancelled` ou `completed`, et masquer le formulaire dans `exchange/show.html.twig`.
- [x] **`exchange_show` charge l'échange même si l'annonce est désactivée** ou si l'autre utilisateur est supprimé : `Exchange $exchange` est résolu sans filtre. Vérifier `announcement.inactivatedAt`.

### Base de données (contraintes d'intégrité)
- [x] `Message::$exchange` et `Message::$sender` sont **nullable**. Un message sans échange ni auteur n'a pas de sens : ajouter `#[ORM\JoinColumn(nullable: false)]`.
- [x] `Wishlist::$book` est **nullable** : même correction.
- [x] Pas de **contrainte d'unicité** sur `exchange (announcement_id, requester_id)`. Le contrôleur vérifie avec `findOneBy`, mais deux clics rapides créent un doublon. Ajouter `#[ORM\UniqueConstraint(columns: ['announcement_id', 'requester_id'])]` et `#[UniqueEntity]`.
- [x] Même chose pour `wishlist (user_id, book_id)`.
- [ ] Migration générée (`migrations/Version20261112134631.php`). **Il reste à réimporter `seed.sql` puis à lancer `doctrine:migrations:migrate`**, car l'ancien seed contenait une demande d'échange en double.

### Sécurité / comptes
- [x] **La déconnexion passe par un lien GET** (`base.html.twig`). N'importe quel site peut te déconnecter via une image. Activer `logout: { enable_csrf: true }` dans `security.yaml` et remplacer le lien par un petit `<form method="post">`.
- [ ] **`isVerified` n'est jamais utilisé.** Il faut choisir : soit mettre en place la vérification d'email (`symfonycasts/verify-email-bundle`) et la contrôler dans `UserChecker`, soit retirer le champ.
- [x] `RegistrationFormType` : `Length(min: 6)` et `PasswordStrength()` font doublon, et le message de `PasswordStrength` est en anglais. Garder `PasswordStrength` avec un `message` en français.

### Dépendances et dépôt
- [x] `composer.json` ne déclare **ni Twig, ni Form, ni Validator, ni CSRF, ni PasswordHasher**. Ils n'arrivent que par EasyAdmin, donc si tu retires EasyAdmin, tout casse. À déclarer explicitement :
  `composer require symfony/twig-bundle symfony/form symfony/validator symfony/security-csrf symfony/password-hasher symfony/asset twig/extra-bundle`
- [ ] Le `name` et la `description` de `composer.json` sont encore ceux du squelette Symfony.
- [x] Retirer du dépôt : `.DS_Store` et `frankenphp/Caddyfile.bak` (fait). Le dossier `..Readme/` reste à déplacer ou supprimer. Ajouter `.DS_Store` et `*.bak` à `.gitignore` :
  `git rm --cached .DS_Store frankenphp/Caddyfile.bak`
- [ ] Beaucoup de fichiers ne sont pas commités (CommentController, ExchangeController, UserChecker, templates/exchange…). Faire des **petits commits thématiques**.

---

## 🟠 2. Qualité du code (lisible et compréhensible)

### Architecture
- [~] **Sortir la logique métier des contrôleurs** et la mettre dans des services : *(fait pour les échanges avec `ExchangeManager` ; reste `AnnouncementPublisher`)*
  - `src/Service/AnnouncementPublisher.php` reçoit les données du formulaire et crée l'annonce et ses livres. C'est le contenu actuel de `BookController::new()`.
  - `src/Service/ExchangeManager.php` gère `request()`, `accept()`, `refuse()`, `complete()` et `cancel()`, avec les changements de statut de l'annonce.
- [ ] **Remplacer la validation faite à la main par des FormType** :
  - `CommentController` : créer `CommentType` (`NotBlank` et `Length(3, 1000)`) au lieu de `mb_strlen` et des flash.
  - `ExchangeController::request()` : créer `ExchangeRequestType` pour le message.
  - Les formulaires gèrent déjà le CSRF : tu peux supprimer les `isCsrfTokenValid` manuels.
- [ ] **Mettre les règles de validation sur les entités** (`#[Assert\NotBlank]`, `#[Assert\Length]`, `#[Assert\PositiveOrZero]` sur `price`…) plutôt que seulement dans les formulaires. Elles s'appliqueront alors aussi dans EasyAdmin.
- [~] **Créer des Voters** au lieu des `if` de droits dans les contrôleurs : *(`ExchangeVoter` fait ; reste `AnnouncementVoter`)*
  - `ExchangeVoter` : `VIEW` (demandeur ou propriétaire), `ACCEPT`, `REFUSE` et `COMPLETE` (propriétaire seulement), `CANCEL` (demandeur).
  - `AnnouncementVoter` : `EDIT` et `DELETE` (propriétaire ou admin), `REQUEST` (connecté, pas propriétaire, annonce disponible).
  - Usage : `#[IsGranted('VIEW', 'exchange')]` dans les contrôleurs et `is_granted('EDIT', announcement)` dans Twig.
- [ ] **Ajouter les relations inverses manquantes** : `Announcement::$user` → `User::$announcements` et `Book::$announcement` → `Announcement::$books`. Cela permet d'écrire `announcement.books` dans Twig au lieu de passer `booksByAnnouncement` partout.

### Entités et enums
- [x] Initialiser les dates dans le constructeur, comme `Announcement`, `Exchange` et `User` le font déjà : `Comment::createdAt`, `Message::sentAt`, `BookPage::uploadedAt` et `Wishlist::addedAt`. Les contrôleurs n'auront plus à appeler `setCreatedAt(new \DateTimeImmutable())`.
- [x] **Mettre les libellés en français dans les enums** plutôt que dans les macros Twig :
  ```php
  enum BookCondition: string {
      case New = 'new'; // ...
      public function label(): string { return match ($this) { self::New => 'Neuf', /* ... */ }; }
  }
  ```
  Dans Twig, `book.bookCondition.label` remplace alors `ui.condition_label()`, et le même libellé sert dans EasyAdmin (`ChoiceField::setChoices`) et dans les formulaires (`choice_label`).
- [ ] Ajouter une méthode `isActive()` sur les entités qui ont un `inactivatedAt` (ou un trait `SoftDeletableTrait` partagé).
- [ ] Remplacer `BookPublicationType::MAX_BOOKS = 100` par une valeur réaliste, par exemple 20.

### Contrôleurs et repositories
- [ ] `HomeController` et `Admin/DashboardController` appellent `$em->getRepository(X::class)`. Injecter plutôt `BookRepository` et `UserRepository`, avec des méthodes nommées (`countActive()`).
- [ ] Supprimer les repositories vides inutiles ou y ajouter les méthodes prévues (`WishlistRepository::findForUser()`…).
- [ ] La recherche (`AnnouncementRepository::searchActive`) ne cherche que dans le titre et la description de l'annonce. Elle doit aussi chercher dans **le titre, l'auteur et l'ISBN des livres**.
- [ ] `ExchangeRepository::findForUser()` renvoie aussi les échanges d'annonces désactivées.

### Templates
- [ ] **Supprimer les ~33 `style="..."` en ligne** (show : 11, exchange/show : 6…) et créer des classes utilitaires dans `app.css` (`.mt-4`, `.ml-auto`, `.stack`…).
- [ ] **Doublon** : `_card.html.twig` réécrit l'affichage du prix au lieu d'utiliser `ui.price()`.
- [ ] **Sortir le JavaScript en ligne** de `base.html.twig` et `exchange/show.html.twig` vers des fichiers. Le mieux est AssetMapper (`composer require symfony/asset-mapper symfony/stimulus-bundle`) avec un contrôleur Stimulus par comportement (menu, autogrow, reveal).
- [ ] Découper `announcement/show.html.twig` (171 lignes) en partials : `_seller_panel.html.twig`, `_book_list.html.twig`, `_comments.html.twig`.
- [ ] Formater les dates en français avec `twig/intl-extra` : `{{ date|format_date('long', locale='fr') }}` affiche « 12 novembre 2026 ».

### Corrigé en plus pendant le nettoyage
- [x] Admin : la liste des échanges et l'édition des livres et des échanges plantaient (erreur 500). Ajout des champs de `ExchangeCrudController` et de `Announcement::__toString()`.
- [x] Locale par défaut passée de `en` à `fr` (`config/packages/translation.yaml`) : messages de validation en français.
- [x] Suppression des blocs `configureFields` générés et commentés dans 5 CRUD EasyAdmin.

### Style de code
- [x] Indentation cassée : `SecurityController::login()`, `DashboardController::configureMenuItems()`, `Book::__toString()` et `User::__toString()`.
- [x] Commentaire inutile `// src/Security/UserChecker.php` et lignes vides en trop dans les repositories.
- [x] Ajouter **PHP-CS-Fixer** (`composer require --dev friendsofphp/php-cs-fixer`) avec les règles `@Symfony`, puis le lancer sur `src/`. Cela règle tous les points ci-dessus d'un coup.
- [ ] Garder une règle claire : **le code en anglais, les textes affichés en français**. Choisir aussi un seul mot entre « commentaire » et « avis » dans l'interface.

---

## 🟢 3. Itérations (nouvelles fonctionnalités)

### Itération 1 — Workflow d'échange complet *(le plus important)*
- [x] Route POST `exchange_transition` (`/exchanges/{id}/accept|refuse|complete|cancel`), protégée par CSRF et par `ExchangeVoter`.
- [x] **Accepter** : échange → `accepted`, annonce → `reserved`, les autres demandes `pending` de cette annonce → `refused`.
- [x] **Terminer** : échange → `completed` avec `completedAt`, annonce → `completed`.
- [x] **Annuler** : échange → `cancelled`. Si l'échange était accepté, l'annonce redevient `available`.
- [x] Afficher les boutons d'action selon le rôle (vendeur ou demandeur) et le statut dans `exchange/show.html.twig`.
- [x] Message automatique dans la conversation à chaque action (« ✓ Demande acceptée… »), envoyé au nom de la personne qui agit.
- [x] Confirmation avant refuser, annuler et terminer (`data-confirm`).
- [ ] Afficher aussi les demandes reçues et leurs boutons sur la page de l'annonce, côté vendeur.
- [ ] Différencier visuellement les messages automatiques (par exemple un champ `Message::$type`).

### Itération 2 — Photos des livres (`BookPage`)
- [ ] Upload d'images dans le formulaire de publication : `FileType` avec `Assert\Image(maxSize: '5M')`, ou VichUploaderBundle.
- [ ] Stocker les fichiers dans `public/uploads/books/` et ajouter ce dossier à `.gitignore`.
- [ ] Afficher la vraie photo sur la carte et sur la page de l'annonce (galerie et miniatures), en gardant la couverture CSS comme repli.
- [ ] Changer l'ordre des photos (`pageOrder`).

### Itération 3 — Gérer mes annonces
- [ ] Page « Mes annonces » (`/me/announcements`) avec les statuts.
- [ ] Modifier une annonce et ses livres.
- [ ] Désactiver une annonce (soft delete via `inactivatedAt`, jamais de `DELETE`).

### Itération 4 — Wishlist
- [ ] Bouton ♥ sur la carte et sur la page de l'annonce, en POST avec CSRF ; plus tard en AJAX avec Stimulus.
- [ ] Page « Ma wishlist ».
- [ ] Bonus : prévenir l'utilisateur quand un livre de sa wishlist est réservé ou de nouveau disponible.

### Itération 5 — Profil utilisateur
- [ ] Page publique `/users/{username}` avec ses annonces et sa date d'inscription.
- [ ] Modifier son profil : username, avatar (upload) et mot de passe.
- [ ] Supprimer son compte (RGPD) : `deletedAt` et anonymisation de l'email et du username.

### Itération 6 — Recherche et filtres
- [ ] Filtres : type (don ou vente), genre, état, prix maximum.
- [ ] Tri : plus récents, prix croissant ou décroissant.
- [ ] Garder les filtres dans l'URL pour pouvoir partager une recherche.

### Itération 7 — Comptes
- [ ] Vérification de l'email à l'inscription (`symfonycasts/verify-email-bundle`).
- [ ] Mot de passe oublié (`symfonycasts/reset-password-bundle`).
- [ ] Mailer en dev : Mailpit, avec un service `mailer` dans `compose.override.yaml`.

### Itération 8 — Messages non lus
- [ ] Ajouter `Message::$readAt` (migration).
- [ ] Afficher un badge du nombre de messages non lus sur « Mes échanges » dans le menu.

### Itération 9 — Modération et pages légales
- [ ] Bouton « Signaler » sur les commentaires et les annonces.
- [ ] Dans EasyAdmin : une action « Désactiver » (qui remplit `inactivatedAt`) à la place de « Supprimer ».
- [ ] Pages CGU et mentions légales, car `cgu_validated_at` est enregistré mais il n'existe aucune page CGU.
- [ ] Pages d'erreur personnalisées : `templates/bundles/TwigBundle/Exception/error404.html.twig`, `error403`, `error`.

### Bonus
- [ ] Préremplir le titre et l'auteur à partir de l'ISBN (API Open Library).
- [ ] Carte ou ville du vendeur pour les remises en main propre.

---

## 🎨 4. Pour une belle app

- [ ] De **vraies photos de livres** : c'est ce qui change le plus le rendu (itération 2).
- [ ] **États vides soignés** partout, avec une illustration et une action : aucun échange, aucune annonce, wishlist vide.
- [ ] **Confirmation avant les actions importantes** (refuser, annuler, désactiver) avec un `<dialog>` HTML natif.
- [ ] **Responsive** : tester chaque page à 375 px de large (formulaire de publication, chat, détail d'annonce).
- [ ] **Accessibilité** :
  - [ ] `alt` sur toutes les images, `label` sur tous les champs (c'est OK pour la plupart).
  - [ ] Focus visible au clavier (`:focus-visible`) sur les boutons et les liens.
  - [ ] Contraste du texte `muted` sur fond beige (vérifier avec l'outil des DevTools).
  - [ ] Respecter `prefers-reduced-motion` pour les animations `.reveal`.
- [ ] **Messages flash** qui se ferment (bouton × ou disparition après 5 s).
- [ ] **Titre de page unique** sur chaque page (`{% block title %}`) et bloc `meta description` surchargeable.
- [ ] **Pagination** de la page Annonces : afficher les numéros de page, pas seulement précédent et suivant.
- [ ] **Chat** : séparateurs par jour (« Aujourd'hui », « Hier »), défilement automatique vers le bas, envoi avec Entrée.
- [ ] Organiser `app.css` (676 lignes) en sections claires (variables, base, composants, pages), ou le découper en plusieurs fichiers avec AssetMapper.

---

## 🧪 5. Tests et outillage

- [ ] **Fixtures** : remplacer `seed.sql` par `doctrine/doctrine-fixtures-bundle` et `zenstruck/foundry` (Faker inclus). Elles se relancent avec `bin/console doctrine:fixtures:load` et restent à jour quand les entités changent.
- [ ] **Tests PHPUnit** (`composer require --dev symfony/test-pack`), au minimum :
  - [ ] `/`, `/announcements`, `/login` et `/register` répondent 200.
  - [ ] Une annonce désactivée renvoie 404 pour un utilisateur et 200 pour un admin.
  - [ ] Un utilisateur tiers reçoit 403 sur `/exchanges/{id}`.
  - [ ] On ne peut pas faire une demande sur sa propre annonce.
  - [ ] Publication d'un livre seul et d'un pack (titre du pack obligatoire, prix obligatoire pour une vente).
  - [ ] Tests unitaires de `ExchangeManager` (transitions de statut autorisées et interdites).
- [ ] **PHPStan** niveau 6 minimum (`phpstan/phpstan` et `phpstan/phpstan-symfony`, `phpstan/phpstan-doctrine`).
- [ ] **CI** (`.github/workflows/ci.yaml`) : ajouter les étapes `php-cs-fixer --dry-run`, `phpstan`, `bin/console doctrine:schema:validate` et `bin/phpunit`.
- [ ] **README** : comment lancer le projet, charger les fixtures, les comptes de test et les captures d'écran.

---

## 📋 Ordre conseillé

| # | Bloc | Pourquoi | État |
|---|------|----------|------|
| 1 | 🔴 Dépendances, dépôt Git, contraintes BDD et migration | Une base saine avant de construire dessus | ✅ fait |
| 2 | 🟠 Libellés dans les enums, dates dans les constructeurs, PHP-CS-Fixer | Rapide, et rend tout le reste plus lisible | ✅ fait |
| 3 | 🟢 Itération 1 (workflow d'échange) avec `ExchangeManager` et `ExchangeVoter` | Le cœur de l'app ne fonctionne pas encore | ✅ fait |
| 4 | 🧪 Fixtures Foundry et premiers tests | Pour ne plus rien casser ensuite | |
| 5 | 🟢 Itérations 2 et 3 (photos, mes annonces) | Le plus visible pour l'utilisateur | |
| 6 | 🎨 Passe interface : styles en ligne, AssetMapper, responsive, accessibilité | | |
| 7 | 🟢 Itérations 4 à 9 selon le temps | | |
