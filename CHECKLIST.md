# Checklist BookShare

Revue du code au 12/11/2026 (entités, contrôleurs, formulaires, repositories, templates, CI).
Coche au fur et à mesure. L'ordre conseillé est en bas du fichier.

**Avancement (12/11/2026) :** 77 points faits, 5 en partie, 35 à faire. Blocs 1 à 6 terminés, itérations 4 à 7 faites, 113 tests au vert. Prochaine étape : itération 8 (messages non lus).

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
- [x] Migration générée et exécutée (`migrations/Version20261112134631.php`), après suppression du doublon d'échange.

### Sécurité / comptes
- [x] **La déconnexion passe par un lien GET** (`base.html.twig`). N'importe quel site peut te déconnecter via une image. Activer `logout: { enable_csrf: true }` dans `security.yaml` et remplacer le lien par un petit `<form method="post">`.
- [x] **`isVerified` est maintenant utilisé** : email de confirmation à l'inscription ; tant que l'adresse n'est pas confirmée, publier et faire une demande sont bloqués (itération 7).
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
- [x] **Sortir la logique métier des contrôleurs** et la mettre dans des services : `ExchangeManager` (échanges) et `AnnouncementPublisher` (publication, modification, retrait).
  - `src/Service/AnnouncementPublisher.php` reçoit les données du formulaire et crée l'annonce et ses livres. C'est le contenu actuel de `BookController::new()`.
  - `src/Service/ExchangeManager.php` gère `request()`, `accept()`, `refuse()`, `complete()` et `cancel()`, avec les changements de statut de l'annonce.
- [~] **Remplacer la validation faite à la main par des FormType** : *(les règles « titre de pack » et « prix d'une vente » sont dans `BookPublicationType` via une contrainte `Callback` ; reste `CommentType` et `ExchangeRequestType`)*
  - `CommentController` : créer `CommentType` (`NotBlank` et `Length(3, 1000)`) au lieu de `mb_strlen` et des flash.
  - `ExchangeController::request()` : créer `ExchangeRequestType` pour le message.
  - Les formulaires gèrent déjà le CSRF : tu peux supprimer les `isCsrfTokenValid` manuels.
- [ ] **Mettre les règles de validation sur les entités** (`#[Assert\NotBlank]`, `#[Assert\Length]`, `#[Assert\PositiveOrZero]` sur `price`…) plutôt que seulement dans les formulaires. Elles s'appliqueront alors aussi dans EasyAdmin.
- [x] **Créer des Voters** au lieu des `if` de droits dans les contrôleurs : `ExchangeVoter` et `AnnouncementVoter` (`EDIT`, `DEACTIVATE`).
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
- [x] La recherche cherche aussi dans **le titre, l'auteur et l'ISBN des livres** (sous-requêtes `EXISTS`).
- [ ] `ExchangeRepository::findForUser()` renvoie aussi les échanges d'annonces désactivées.

### Templates
- [x] **Styles en ligne supprimés** : classes utilitaires (`.mt-*`, `.mb-*`, `.ml-auto`, `.cluster`…) et petits composants dans `app.css`. Seule exception voulue : les variables CSS de l'étagère décorative (`--c`, `--w`, `--h`), qui sont des données.
- [ ] **Doublon** : `_card.html.twig` réécrit l'affichage du prix au lieu d'utiliser `ui.price()`.
- [x] **JavaScript sorti des templates** : AssetMapper + Stimulus, plus aucun `<script>` ni `onerror` dans les templates. Contrôleurs dans `assets/controllers/` : `nav`, `reveal`, `photo`, `gallery`, `chat`, `tabs`, `book-form`, `flash`, `confirm`. Le CSS est passé de `public/css/` à `assets/styles/app.css`.
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
- [x] Upload de 5 photos max par livre (JPEG/PNG/WebP, 5 Mo) dans le formulaire de publication et de modification, via `BookPhotoUploader` (nom de fichier aléatoire).
- [x] Fichiers dans `public/uploads/books/` (ignoré par Git) ; en test dans `var/test-uploads/`, nettoyé après chaque test.
- [x] Vraie photo sur les cartes, dans la liste des livres et en galerie (miniatures cliquables) sur la page de l'annonce ; couverture CSS en repli.
- [x] Supprimer une photo depuis la page de modification (soft delete).
- [x] Repli automatique : si une photo ne charge pas (fichier supprimé, hébergeur en panne comme picsum.photos le 12/11), la couverture générée s'affiche à sa place.
- [ ] Changer l'ordre des photos par glisser-déposer (`pageOrder` est déjà rempli dans l'ordre d'envoi).
- [ ] Redimensionner les photos à l'envoi (nécessite l'extension GD ou Imagick, absente du conteneur).
- [ ] Charger les photos en une requête sur la liste des annonces (aujourd'hui une requête par livre).
- [ ] Remplacer les URL `picsum.photos` du seed et de `BookPageFactory` par quelques images locales (dans `assets/` ou `public/`), pour ne plus dépendre d'un service extérieur.

### Itération 3 — Gérer mes annonces
- [x] Page « Mes annonces » (`/announcements/mine`) avec statuts, demandes en attente et lien dans le menu.
- [x] Modifier une annonce et ses livres (même formulaire que la publication ; livres retirés désactivés, pas supprimés).
- [x] Retirer une annonce (soft delete via `inactivatedAt`) : les demandes en attente sont refusées, impossible si l'annonce est réservée.

### Itération 4 — Wishlist
- [x] Bouton ♥ par livre sur la page de l'annonce (POST + CSRF, `WishlistManager`), état plein/vide, absent sur ses propres livres. Retirer désactive la ligne, ré-ajouter la réactive.
- [ ] Bouton ♥ aussi sur les cartes de la liste des annonces, et en AJAX (Stimulus) pour ne pas recharger la page.
- [x] Page « Ma wishlist » (`/wishlist`, lien dans le menu) : seulement les livres encore en ligne, avec le statut de l'annonce et le bouton ♥ pour retirer.
- [ ] Bonus : prévenir l'utilisateur quand un livre de sa wishlist est réservé ou de nouveau disponible.

### Itération 5 — Profil utilisateur
- [x] Page publique `/users/{username}` : avatar, date d'inscription, annonces en ligne et nombre d'échanges terminés. Les pseudos sont cliquables partout (cartes, annonce, commentaires, conversation).
- [x] Page « Mon profil » (`/profile`, lien sur le pseudo dans le menu) : pseudo, photo de profil (upload ou suppression) et mot de passe (l'ancien est redemandé). Les règles du pseudo sont sur l'entité `User` (3 à 30 caractères, lettres sans accent, chiffres, `.`, `-`, `_`), donc communes avec l'inscription.
- [x] Supprimer son compte (RGPD, `AccountManager::delete`) avec mot de passe et boîte de confirmation : annonces retirées, échanges en cours annulés ou refusés, wishlist vidée, email/pseudo/avatar/mot de passe effacés, `deletedAt` bloque la connexion ; les commentaires des comptes supprimés sont masqués.
- [x] Service `ImageStorage` commun aux photos de livres et aux avatars (`public/uploads/books`, `public/uploads/avatars`).

### Itération 6 — Recherche et filtres
- [x] Filtres : type (don ou vente), genre, état, prix maximum, « disponibles uniquement » (`AnnouncementSearch` lit l'URL et ignore les valeurs invalides).
- [x] Tri : plus récents, prix croissant ou décroissant (un don compte pour 0 €).
- [x] Filtres gardés dans l'URL (recherche partageable) et dans la pagination.

### Itération 7 — Comptes
- [x] Vérification de l'email à l'inscription : lien signé et daté (`verify-email-bundle`), valable même sans être connecté ; bandeau « Renvoyer le lien » tant que l'adresse n'est pas confirmée ; publier et faire une demande bloqués en attendant.
- [x] Mot de passe oublié (`reset-password-bundle`, entité `ResetPasswordRequest` + migration) : lien valable 1 h et à usage unique, jeton retiré de l'URL, même réponse que l'adresse existe ou non, une demande toutes les 15 min. Lien « Mot de passe oublié ? » sur la page de connexion.
- [x] Mailer en dev : Mailpit (service `mailer` de `compose.override.yaml`), emails lisibles sur http://localhost:8025 ; en test `MAILER_DSN=null://null`. Emails en HTML (`templates/emails/`), envoyés par `AccountMailer`.
- [x] Formulaire `NewPasswordType` (mot de passe répété + `PasswordStrength`) partagé entre « Mon profil » et le mot de passe oublié.

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

- [x] De **vraies photos de livres** : c'est ce qui change le plus le rendu (itération 2).
- [~] **États vides soignés** : faits pour « Mes annonces », « Mes échanges », « Ma wishlist » et la recherche sans résultat ; reste l'accueil sans annonce.
- [x] **Confirmation avant les actions importantes** (refuser, annuler, terminer, retirer une annonce) dans une vraie boîte `<dialog>` (contrôleur `confirm`, macro `ui.confirm()`), avec repli sur `window.confirm()`.
- [x] **Responsive** vérifié à 375 px dans le navigateur (accueil, annonces, détail, conversation, publication, Mes annonces) : aucun débordement horizontal. Le menu passe en « burger » dès 1100 px, car connecté il ne tenait plus sur une ligne.
- [x] Vérification visuelle au navigateur (12/11) : elle a révélé et permis de corriger le bouton « Ajouter un livre au pack » cassé (prototype échappé deux fois, test de non-régression ajouté), le texte des couvertures qui débordait des vignettes de « Mes annonces » et la photo absente du panneau « L'annonce » dans une conversation.
- [x] 2e vérification (itérations 4 et 6) : barre de recherche qui débordait sur mobile corrigée, et `scroll-margin-top` pour que le livre ne soit pas caché sous l'en-tête après un clic ♥.
- [ ] Ajouter un fichier `translations/messages.fr.yaml` (ou `translation_domain: false` sur les formulaires) : la barre de debug signale des placeholders sans traduction depuis le passage de la locale en `fr`.
- [x] **Accessibilité** :
  - [x] `alt` sur toutes les images (photos, miniatures décoratives en `alt=""`), `label` sur tous les champs. Dans la boîte de confirmation, le focus va sur « Annuler » : un appui sur Entrée ne confirme pas une action destructive.
  - [x] Focus visible au clavier (`:focus-visible`) sur les boutons et les liens. Échap ferme le menu mobile.
  - [x] Contraste : `--muted` passe à `#6b6458` (5,2:1) et le bouton terracotta à `#9c5838` (4,8:1), au-dessus du seuil WCAG AA de 4,5:1.
  - [x] `prefers-reduced-motion` respecté pour toutes les animations et transitions (et `.reveal` affiché directement).
  - [x] Onglets de « Mes échanges » avec `role="tab"` et `aria-selected`, bouton de fermeture des messages avec `aria-label`.
- [x] **Messages flash** qui se ferment (bouton ×) ; les messages de succès disparaissent seuls après 6 s.
- [ ] **Titre de page unique** sur chaque page (`{% block title %}`) et bloc `meta description` surchargeable.
- [ ] **Pagination** de la page Annonces : afficher les numéros de page, pas seulement précédent et suivant.
- [~] **Chat** : défilement automatique, envoi avec Entrée et séparateur « Aujourd'hui » faits ; reste « Hier ».
- [~] Organiser `app.css` (déplacé dans `assets/styles/`, ~770 lignes) : sections utilitaires, composants et accessibilité ajoutées ; reste à le découper en plusieurs fichiers.

---

## 🧪 5. Tests et outillage

- [x] **Fixtures** Foundry : `src/Factory/*` et `src/Story/AppStory.php`, chargées avec `bin/console foundry:load-fixtures main`. Les échanges passent par `ExchangeManager`, donc toujours cohérents.
- [x] **Tests PHPUnit** (113 tests, logs muets en test, base SQLite jetable `var/test.db` via `TEST_DATABASE_URL`) :
  - [x] `/`, `/announcements`, `/login` et `/register` répondent 200.
  - [x] Une annonce désactivée renvoie 404 pour un utilisateur et 200 pour un admin.
  - [x] Un utilisateur tiers reçoit 403 sur `/exchanges/{id}`.
  - [x] On ne peut pas faire une demande sur sa propre annonce.
  - [x] Publication d'un livre seul et d'un pack (titre du pack obligatoire, prix obligatoire pour une vente).
  - [x] Tests unitaires de `ExchangeManager` (transitions de statut autorisées et interdites).
  - [x] Tests unitaires des Voters (`ExchangeVoter`, `AnnouncementVoter`), connexion/déconnexion, « Mes annonces », photos (envoi, fichier refusé, suppression, droits).
  - [x] Wishlist (ajout, retrait, réactivation, cœur plein, propriétaire, annonces retirées) et recherche (12 combinaisons de filtres et de tris, valeurs invalides).
  - [x] Profil : page publique, pseudo et avatar, règles du pseudo, changement de mot de passe, suppression du compte (anonymisation, échanges, annonces, wishlist) et mot de passe incorrect.
  - [x] Comptes : email de confirmation à l'inscription et lien qui valide le compte, lien falsifié refusé, blocage tant que non confirmé, renvoi du lien, mot de passe oublié de bout en bout (lien à usage unique), adresse inconnue ou compte supprimé sans email.
- [ ] **PHPStan** niveau 6 minimum (`phpstan/phpstan` et `phpstan/phpstan-symfony`, `phpstan/phpstan-doctrine`).
- [~] **CI** : `php-cs-fixer check`, migrations + `doctrine:schema:validate` et `bin/phpunit` ajoutés. Reste PHPStan.
- [ ] **README** : comment lancer le projet, charger les fixtures, les comptes de test et les captures d'écran.

---

## 📋 Ordre conseillé

| # | Bloc | Pourquoi | État |
|---|------|----------|------|
| 1 | 🔴 Dépendances, dépôt Git, contraintes BDD et migration | Une base saine avant de construire dessus | ✅ fait |
| 2 | 🟠 Libellés dans les enums, dates dans les constructeurs, PHP-CS-Fixer | Rapide, et rend tout le reste plus lisible | ✅ fait |
| 3 | 🟢 Itération 1 (workflow d'échange) avec `ExchangeManager` et `ExchangeVoter` | Le cœur de l'app ne fonctionne pas encore | ✅ fait |
| 4 | 🧪 Fixtures Foundry et premiers tests | Pour ne plus rien casser ensuite | ✅ fait |
| 5 | 🟢 Itérations 2 et 3 (photos, mes annonces) | Le plus visible pour l'utilisateur | ✅ fait |
| 6 | 🎨 Passe interface : styles en ligne, AssetMapper, responsive, accessibilité | Code plus propre et app plus agréable | ✅ fait |
| 7 | 🟢 Itérations 4 à 9 selon le temps | | 🚧 4, 5, 6 et 7 faites ; restent 8 (non lus) et 9 (modération) |
