# BookShare — 50 questions pour l'oral

> Fiche de révision : chaque réponse donne **les fichiers** concernés, **comment ça marche** étape par étape, et **la phrase à retenir**.
> État du projet au 24/11/2026 : Symfony 8.1, 143 tests au vert.

**Le réflexe pour toute question « comment fonctionne X ? »** : suivre le trajet d'une requête.

```
Route (#[Route] dans un Controller)
  → Voter (a-t-il le droit ?)
  → Formulaire / jeton CSRF (les données sont-elles valides et viennent-elles du site ?)
  → Service (règle métier : on change l'état)
  → Repository / Entité (lecture / écriture en base via Doctrine)
  → Template Twig (HTML) → contrôleur Stimulus (comportement JS)
```

---

## Partie A — Vue d'ensemble et architecture

### 1. Qu'est-ce que BookShare, en une phrase ?
Un site où les membres **donnent ou vendent leurs livres d'occasion** : on publie une annonce (un livre ou un pack), les autres font une demande, discutent dans une conversation, puis le vendeur accepte et termine l'échange.
**À retenir :** annonce → demande → conversation → acceptée → terminée.

### 2. Quelle est la stack technique ?
- **Docker + FrankenPHP** (serveur web Caddy + PHP 8.5) : `compose.yaml`, `compose.override.yaml`, `Dockerfile`.
- **Symfony 8.1** (framework PHP), **Doctrine** (base MySQL 8), **Twig** (vues).
- **AssetMapper + Stimulus** pour le CSS/JS, **EasyAdmin** pour l'admin, **Symfony Mailer + Mailpit** pour les emails.
- **PHPUnit + Foundry** pour les tests, **PHP-CS-Fixer** et **GitHub Actions** pour la qualité.

### 3. Comment le projet est-il organisé (les dossiers) ?
| Dossier | Rôle |
|---|---|
| `src/Controller/` | reçoit les requêtes, appelle les autres couches, choisit le template |
| `src/Service/` | **la logique métier** (ce que fait l'app) |
| `src/Security/Voter/` | **qui a le droit** de faire quoi |
| `src/Repository/` | **toutes les requêtes** en base |
| `src/Entity/` | les tables sous forme de classes PHP |
| `src/Enum/` | les listes de valeurs (statuts, types, états) |
| `src/Form/` | les formulaires et leurs règles |
| `templates/` | les pages Twig |
| `assets/` | CSS (`styles/app.css`) et contrôleurs Stimulus (`controllers/`) |
| `migrations/` | l'historique de la structure de la base |
| `tests/` | les tests automatiques |

### 4. Pourquoi des Services au lieu de tout mettre dans les contrôleurs ?
Pour séparer les responsabilités : le **contrôleur** gère le HTTP (requête, redirection, message flash), le **service** gère la règle métier.
- Exemple : `ExchangeController::transition()` ne fait que vérifier les droits et le CSRF, puis appelle `ExchangeManager::accept()`.
- Avantages : code **réutilisable** (les fixtures `AppStory` et `AccountManager` appellent aussi `ExchangeManager`), **testable** sans navigateur (`tests/Service/ExchangeManagerTest.php`), contrôleurs courts et lisibles.
**À retenir :** « contrôleur = chef d'orchestre, service = métier ».

### 5. Quels sont les services et que fait chacun ?
| Service (`src/Service/`) | Rôle |
|---|---|
| `ExchangeManager` | créer une demande, envoyer un message, accepter / refuser / terminer / annuler |
| `ExchangeNotifier` | envoyer un email à l'autre participant à chaque événement d'un échange |
| `AnnouncementPublisher` | publier, modifier, retirer une annonce et ses livres |
| `WishlistManager` | ajouter / retirer un livre de la wishlist (♥) |
| `AccountManager` | profil (pseudo, avatar), mot de passe, suppression du compte (RGPD) |
| `AccountMailer` | emails de confirmation d'adresse et de mot de passe oublié |
| `ImageStorage` | enregistrer / supprimer une image envoyée (`public/uploads/…`) |
| `BookPhotoUploader` | ajouter une photo à un livre (s'appuie sur `ImageStorage`) |

### 6. Comment Symfony sait-il quel contrôleur appeler pour une URL ?
Grâce aux **attributs `#[Route]`** au-dessus des méthodes, chargés par `config/routes.yaml` (`resource: routing.controllers`).
- Ex. `#[Route('/{id}', name: 'announcement_show', requirements: ['id' => '\d+'], methods: ['GET'])]` dans `AnnouncementController`.
- `requirements` limite l'id à des chiffres, `methods` limite aux verbes HTTP voulus (les actions qui modifient sont en **POST**).
- Commande pour toutes les voir : `bin/console debug:router`.

### 7. Comment un service arrive-t-il dans un contrôleur (injection de dépendances) ?
Par **autowiring** (`config/services.yaml`, `autowire: true`) : il suffit de typer un paramètre (`AnnouncementPublisher $publisher`) dans la méthode ou le constructeur, Symfony fournit l'objet.
- Les paramètres de configuration sont injectés avec `#[Autowire('%app.uploads_dir%')]` (voir `ImageStorage`).
- `#[CurrentUser] User $user` injecte directement l'utilisateur connecté.

### 8. Comment lancer le projet et quels sont les comptes de test ?
- `docker compose up -d` → site sur `https://localhost`, admin sur `/admin`, emails sur `http://localhost:8025` (Mailpit), phpMyAdmin sur `http://localhost:8080`.
- Données de démo : `bin/console foundry:load-fixtures main` (fichier `src/Story/AppStory.php`) ou import de `seed.sql`.
- Comptes : `admin@bookswap.test` (admin), `alice@…`, `bob@…` — mot de passe `password`.

---

## Partie B — Annonces, livres, photos

### 9. Quelle est la différence entre une annonce et un livre ?
- `src/Entity/Announcement.php` : l'**annonce** (titre, description, type don/vente, prix, statut, vendeur).
- `src/Entity/Book.php` : un **livre** de l'annonce (titre, auteur, ISBN, genre, état). Relation `ManyToOne` vers l'annonce.
- Une annonce peut contenir **plusieurs livres** : c'est un **pack**, avec un seul prix pour l'ensemble.

### 10. Comment fonctionne la publication d'une annonce ?
**Fichiers :** `BookController::new()`, `Form/BookPublicationType.php` + `Form/BookItemType.php`, `Service/AnnouncementPublisher::publish()`, `templates/book/new.html.twig`, `assets/controllers/book_form_controller.js`.
1. Route `GET|POST /books/new`, réservée aux connectés (`#[IsGranted('ROLE_USER')]`) **et** aux emails confirmés (`$user->isVerified()`, sinon redirection + message).
2. Le formulaire `BookPublicationType` contient les champs de l'annonce + une **collection** `books` de `BookItemType` (un sous-formulaire par livre).
3. Au POST, `handleRequest()` remplit le formulaire, `isValid()` vérifie toutes les contraintes (voir Q12).
4. `AnnouncementPublisher::publish()` crée l'`Announcement`, un `Book` par entrée, enregistre les photos, puis `flush()`.
5. Message flash + redirection vers la page de l'annonce.
**À retenir :** « le contrôleur valide, le service crée, Doctrine enregistre ».

### 11. Comment ajoute-t-on plusieurs livres dans un pack sans recharger la page ?
- `BookPublicationType` : le champ `books` est un `CollectionType` avec `allow_add`, `allow_delete` et un **prototype** (le HTML d'un livre vide, avec `__name__` à la place de l'index).
- `templates/book/new.html.twig` passe ce prototype au contrôleur Stimulus dans `data-book-form-prototype-value`.
- `assets/controllers/book_form_controller.js` : `add()` remplace `__name__` par un nouvel index et insère le bloc ; `remove()` le supprime ; `refresh()` renumérote « Livre 1, Livre 2… » ; `syncPrice()` désactive le prix pour un don.
- Limite : `BookPublicationType::MAX_BOOKS`.

### 12. Où sont les règles de validation d'une annonce ?
À deux niveaux :
- **Formulaire** (`BookPublicationType::validatePublication()`, contrainte `Callback`) : un **pack doit avoir un titre**, une **vente doit avoir un prix**. Les règles champ par champ sont dans `BookItemType` (titre obligatoire, ISBN valide `Isbn`, état obligatoire, photos `Image`).
- **Entités** (`Announcement.php`, `Book.php`, attributs `#[Assert\…]`) : titre non vide, prix ≥ 0, et `Announcement::validatePrice()` (une vente a un prix, **un don n'en a pas**). Elles s'appliquent aussi dans **EasyAdmin**.
- Testé dans `tests/Entity/EntityValidationTest.php` et `tests/Functional/BookPublicationTest.php`.

### 13. Comment fonctionne l'envoi de photos ?
**Fichiers :** `BookItemType` (champ `photos`), `Service/BookPhotoUploader.php`, `Service/ImageStorage.php`, `Entity/BookPage.php`.
1. Champ `FileType` multiple, contraintes : 5 photos max (`Count`), JPEG/PNG/WebP de 5 Mo max (`Image`).
2. `AnnouncementPublisher::fillBook()` appelle `BookPhotoUploader::attach()` pour chaque fichier.
3. `ImageStorage::store()` **renomme le fichier avec un nom aléatoire** (`random_bytes`) et l'extension devinée **d'après le contenu** (`guessExtension()`), puis le déplace dans `public/uploads/books/`.
4. Une `BookPage` est créée avec l'URL et un `pageOrder`.
**Sécurité :** on ne fait jamais confiance au nom ni à l'extension envoyés par le navigateur.

### 14. Comment supprime-t-on une photo ?
- Bouton « Supprimer » dans la page de modification (`templates/book/_book_entry.html.twig`) : c'est un bouton du formulaire principal avec `formaction` vers une autre route (un formulaire HTML ne peut pas en contenir un autre).
- `BookController::deletePhoto()` (`POST /books/photos/{id}/delete`) : vérifie le droit `ANNOUNCEMENT_EDIT`, le jeton CSRF, met `inactivatedAt` sur la `BookPage` (suppression douce), puis `ImageStorage::delete()` efface le fichier du disque.
- `ImageStorage::delete()` refuse les chemins hors du dossier d'upload ou contenant `..` (protection contre le *path traversal*).

### 15. Comment s'affiche une annonce (page détail) ?
**Fichiers :** `AnnouncementController::show()`, `templates/announcement/show.html.twig`, `templates/_partials/ui.html.twig`.
1. Un admin charge l'annonce même désactivée (`find()`), les autres seulement si elle est active (`AnnouncementRepository::findActiveById()`), sinon **404**.
2. On récupère les livres actifs, les commentaires, la demande éventuelle de l'utilisateur, les livres déjà dans sa wishlist.
3. Pour un pack, `buildLotSummary()` calcule auteurs, genres et nombre de livres par état.
4. Le template affiche la galerie, les livres (avec le ♥), les questions, et le panneau d'action adapté : « Se connecter », « C'est ton annonce », « Ouvrir la conversation » ou « Demander ce livre ».

### 16. Comment modifie-t-on une annonce ?
**Fichiers :** `AnnouncementController::edit()`, `AnnouncementPublisher::toFormData()` et `update()`, `Security/Voter/AnnouncementVoter.php`.
1. `#[IsGranted(AnnouncementVoter::EDIT, subject: 'announcement')]` : seul le **propriétaire**, et seulement si l'annonce est **en ligne et disponible**.
2. `toFormData()` transforme l'annonce et ses livres en tableau pour **pré-remplir** le même formulaire que la publication (template `book/new.html.twig` réutilisé).
3. `update()` associe les livres **par position** : les premiers sont modifiés, les nouveaux créés, ceux en trop **désactivés** (jamais supprimés).

### 17. Comment « supprime »-t-on (retire) une annonce ?
**Fichiers :** `AnnouncementController::deactivate()`, `AnnouncementPublisher::deactivate()`, `AnnouncementVoter` (`DEACTIVATE`), `templates/announcement/_deactivate_form.html.twig`.
1. Bouton « Retirer » (POST + CSRF), avec **boîte de confirmation** (`confirm_controller.js`).
2. Droit : propriétaire **ou admin**, annonce en ligne, et **pas réservée**.
3. Le service refuse si l'annonce est réservée (`AnnouncementException`), **refuse automatiquement les demandes en attente** (via `ExchangeManager::refuse()`), puis met `inactivatedAt`.
**À retenir :** on ne fait **jamais de DELETE SQL**, c'est une **suppression douce** (*soft delete*).

### 18. Pourquoi la suppression douce (soft delete) partout ?
- Les entités sont liées (annonce → livres → photos, échange → messages) : un `DELETE` casserait les clés étrangères ou effacerait les données des autres (la conversation de l'acheteur par exemple).
- On garde un **historique** et l'admin peut **voir / réactiver**.
- Mise en œuvre : champs `inactivatedAt` (et `deletedAt` pour les comptes) ; les repositories filtrent `… IS NULL` ; EasyAdmin a l'action DELETE **désactivée** (`DashboardController::configureActions()`).

### 19. Comment fonctionne la page « Mes annonces » ?
- `AnnouncementController::mine()` (`/announcements/mine`, connecté).
- `AnnouncementRepository::findByOwner()` : toutes ses annonces, **y compris retirées** (les en ligne d'abord).
- `countPendingRequestsByOwner()` : une requête `GROUP BY` qui compte les demandes en attente par annonce.
- Template `announcement/mine.html.twig` : statut, « X demandes en attente », boutons Modifier / Retirer affichés selon `is_granted()`.

---

## Partie C — Recherche

### 20. Comment fonctionne la barre de recherche ?
**Fichiers :** `templates/announcement/index.html.twig`, `src/Model/AnnouncementSearch.php`, `AnnouncementController::index()`, `AnnouncementRepository::searchActive()`.
1. C'est un **formulaire GET** (`?q=…`) : la recherche est dans l'URL, donc **partageable** et compatible bouton « retour ».
2. `AnnouncementSearch::fromRequest()` lit et **nettoie** les paramètres de l'URL.
3. `searchActive()` construit une requête Doctrine (QueryBuilder) : `a.title LIKE :term OR a.description LIKE :term` **OU** `EXISTS` (un livre actif de l'annonce dont le **titre, l'auteur ou l'ISBN** contient le mot).
4. Le terme est passé en **paramètre** (`setParameter`) : **pas d'injection SQL possible**.
**À retenir :** « GET + DTO + QueryBuilder paramétré + sous-requête EXISTS sur les livres ».

### 21. Comment fonctionnent les filtres et le tri ?
Même formulaire GET (`index.html.twig`), champs `type`, `genre`, `condition`, `max_price`, `available`, `sort`.
- `AnnouncementSearch::fromRequest()` utilise `AnnouncementType::tryFrom()` / `BookCondition::tryFrom()` : une valeur inconnue devient `null` → **une URL bricolée ne provoque jamais d'erreur**, le filtre est ignoré.
- Dans `searchActive()` : `a.type = :type`, `EXISTS` pour le genre / l'état d'un livre, `a.price IS NULL OR a.price <= :maxPrice` (un don passe toujours), `a.status = available`.
- Tri prix : `COALESCE(a.price, 0) AS HIDDEN sortPrice` (un don compte pour 0 €).
- La liste des genres vient de `BookRepository::findGenres()` (`DISTINCT`).

### 22. Comment fonctionne la pagination ?
- `AnnouncementController::PER_PAGE = 12`, page lue dans `?page=`.
- `searchActive()` applique `setFirstResult()` / `setMaxResults()` et renvoie un **`Paginator` Doctrine**, qui sait compter le total (`count($result)`) même avec des jointures.
- Les liens Précédent/Suivant gardent les filtres : `path('announcement_index', app.request.query.all|merge({page: page + 1}))`.

### 23. Pourquoi les annonces d'un utilisateur désactivé n'apparaissent-elles pas ?
`AnnouncementRepository::createActiveQueryBuilder()` est la **base de toutes les requêtes publiques** : jointure sur le vendeur et filtres `a.inactivatedAt IS NULL`, `u.inactivatedAt IS NULL`, `u.deletedAt IS NULL`. Une seule méthode = une seule règle, réutilisée partout (accueil, recherche, détail, profil).

---

## Partie D — Échanges et conversation

### 24. Comment fonctionne une demande d'échange ?
**Fichiers :** `ExchangeController::request()`, `Service/ExchangeManager::request()`, `templates/announcement/show.html.twig`.
1. Formulaire « Demander ce livre » (POST `/exchanges/request/{id}`, CSRF).
2. Contrôles du contrôleur : annonce active, jeton CSRF, **email confirmé**, demande déjà existante → on renvoie vers la conversation, message ≤ 2000 caractères.
3. `ExchangeManager::request()` refuse sa **propre annonce** ou une annonce **non disponible**, crée l'`Exchange` (statut `pending`) + le premier `Message` (texte par défaut si vide).
4. `ExchangeNotifier` prévient le vendeur par email.
5. Garantie en base : `UniqueConstraint` sur (`announcement_id`, `requester_id`) → une seule demande par personne et par annonce.

### 25. Quel est le cycle de vie d'un échange ?
Défini dans `src/Enum/ExchangeStatus.php` et appliqué par `ExchangeManager` :
```
pending ──accepter──► accepted ──terminer──► completed
   ├──refuser──► refused       └──annuler──► cancelled (annonce de nouveau disponible)
   └──annuler──► cancelled
```
L'annonce suit : `available` → `reserved` (acceptée) → `completed` (terminée).
`ExchangeStatus::isOpen()` = `pending` ou `accepted` (on peut encore écrire).

### 26. Que se passe-t-il quand le vendeur accepte une demande ?
`ExchangeManager::accept()` :
1. vérifie que l'échange est `pending` et l'annonce `available` ;
2. échange → `accepted`, annonce → `reserved`, message automatique dans la conversation ;
3. **toutes les autres demandes en attente** de cette annonce passent en `refused`, avec un message « réservée pour quelqu'un d'autre » ;
4. **après l'enregistrement**, emails : « acceptée » à l'acheteur, « réservée pour quelqu'un d'autre » aux autres.

### 27. Comment évite-t-on que deux clics simultanés acceptent deux demandes sur la même annonce ?
`ExchangeManager::transition()` (concurrence) :
1. ouvre une **transaction** (`beginTransaction()`) ;
2. relit l'annonce puis l'échange avec un **verrou pessimiste** (`LockMode::PESSIMISTIC_WRITE` = `SELECT … FOR UPDATE`) : la 2e requête **attend** que la 1re ait fini ;
3. refait les vérifications sur des données **à jour** (la 2e voit l'annonce déjà `reserved` → `ExchangeException`) ;
4. `flush()` + `commit()`, ou `rollback()` en cas d'erreur.
L'annonce est toujours verrouillée **avant** l'échange, dans le même ordre, pour éviter un *deadlock*. Testé dans `ExchangeManagerTest`.

### 28. Qui peut accepter, refuser, terminer, annuler ? Où est-ce décidé ?
Dans `src/Security/Voter/ExchangeVoter.php` (`voteOnAttribute()`) :
| Droit | Qui | Quand |
|---|---|---|
| `EXCHANGE_VIEW` | demandeur ou vendeur | toujours |
| `EXCHANGE_MESSAGE` | demandeur ou vendeur | échange ouvert |
| `EXCHANGE_ACCEPT` / `REFUSE` | vendeur | `pending` |
| `EXCHANGE_COMPLETE` | vendeur | `accepted` |
| `EXCHANGE_CANCEL` | demandeur (ouvert) ou vendeur (`accepted`) | |
Utilisé dans le contrôleur (`denyAccessUnlessGranted`, `#[IsGranted]`) **et** dans Twig (`is_granted()` pour n'afficher que les bons boutons). Testé dans `tests/Security/ExchangeVoterTest.php`.

### 29. Comment fonctionne la route unique des actions (accepter/refuser/terminer/annuler) ?
`ExchangeController::transition()` : route `POST /exchanges/{id}/{action}` avec `requirements: action = accept|refuse|complete|cancel`.
- La constante `TRANSITIONS` associe chaque action à : son **droit** (Voter), la **méthode** d'`ExchangeManager`, le **message de succès**.
- Ordre : 1) droits (`denyAccessUnlessGranted` → 403), 2) jeton CSRF, 3) appel du service dans un `try/catch (ExchangeException)` → message flash d'erreur si la transition est impossible.

### 30. Comment fonctionne la conversation (chat) ?
**Fichiers :** `ExchangeController::show()`, `Form/ExchangeMessageType.php`, `ExchangeManager::sendMessage()`, `templates/exchange/show.html.twig`, `assets/controllers/chat_controller.js`.
1. `#[IsGranted(ExchangeVoter::VIEW, subject: 'exchange')]` : seuls les deux participants, sinon 403 ; 404 si l'annonce est retirée.
2. Si l'échange est clos (`MESSAGE` refusé), le formulaire est remplacé par « Cet échange est clos ».
3. `sendMessage()` enregistre le message et envoie un email **seulement quand la personne prend la parole** (anti-spam : 3 messages d'affilée = 1 email).
4. `chat_controller.js` : défile en bas, agrandit la zone de saisie, **Entrée envoie**, Maj+Entrée = retour à la ligne.

### 31. Comment sont envoyées les notifications d'échange par email ?
`src/Service/ExchangeNotifier.php` + `templates/emails/exchange_notification.html.twig`.
- `notify($exchange, $actor, $event)` écrit à **l'autre participant** (`otherParticipant()`), sauf si son compte est supprimé/désactivé.
- Objet selon l'événement (constante `SUBJECTS`), email HTML via `TemplatedEmail`.
- Appelé **après** le commit : on n'annonce jamais un changement qui a échoué.
- Si l'envoi échoue (`TransportExceptionInterface`), l'action n'est pas annulée : on l'écrit dans les **logs**.
- Testé dans `tests/Service/ExchangeNotificationTest.php`.

### 32. Comment fonctionne la page « Mes échanges » ?
`ExchangeController::index()` → `ExchangeRepository::findForUser()` : les échanges où je suis **demandeur OU vendeur** (`e.requester = :user OR a.user = :user`), avec jointures pour éviter les requêtes en boucle. Les onglets Tous / Reçus / Envoyés sont gérés côté navigateur par `assets/controllers/tabs_controller.js` (masque les lignes selon `data-kind`).

---

## Partie E — Comptes et sécurité

### 33. Comment fonctionne l'inscription ?
**Fichiers :** `RegistrationController::register()`, `Form/RegistrationFormType.php`, `Entity/User.php`, `Service/AccountMailer.php`.
1. Formulaire lié à l'entité `User` (`data_class`) : pseudo, email, mot de passe (`PasswordStrength`), CGU (`IsTrue`).
2. Règles du pseudo **sur l'entité** (`#[Assert\Regex]`, longueur 3-30) et unicité email/pseudo (`#[UniqueEntity]`).
3. Mot de passe **haché** (`UserPasswordHasherInterface`), date des CGU enregistrée, `isVerified = false`.
4. Email de confirmation envoyé, puis **connexion automatique** (`Security::login()`).

### 34. Comment fonctionne la connexion ?
**Fichiers :** `config/packages/security.yaml`, `Security/LoginFormAuthenticator.php`, `Security/UserChecker.php`, `SecurityController::login()`, `templates/security/login.html.twig`.
1. Le *provider* charge l'utilisateur par son **email** (entité `User`).
2. `LoginFormAuthenticator::authenticate()` lit email, mot de passe et jeton CSRF, et crée un `Passport` (badges `UserBadge`, `PasswordCredentials`, `CsrfTokenBadge`).
3. `UserChecker::checkPreAuth()` **bloque les comptes désactivés ou supprimés** (« Ce compte est désactivé. »).
4. Succès : retour à la page demandée avant la connexion (`TargetPathTrait`) ou à l'accueil.
5. `login_throttling` : **5 essais ratés par minute** maximum (anti force brute, avec `symfony/rate-limiter`).

### 35. Comment sont stockés les mots de passe ?
Jamais en clair : `password_hashers: auto` dans `security.yaml` choisit le meilleur algorithme disponible (bcrypt/argon2). On hache avec `UserPasswordHasherInterface::hashPassword()` et on vérifie avec `isPasswordValid()`. `UserRepository::upgradePassword()` re-hache automatiquement si l'algorithme évolue.

### 36. Comment fonctionne la déconnexion ?
`security.yaml` → `logout: { path: app_logout, enable_csrf: true }`. Le bouton « Déconnexion » de `base.html.twig` est un **formulaire POST avec jeton CSRF** : un simple lien (ou une image piégée sur un autre site) ne peut pas déconnecter l'utilisateur. `SecurityController::logout()` est vide : le pare-feu intercepte la route.

### 37. Qu'est-ce que le CSRF et comment le projet s'en protège-t-il ?
CSRF = un site malveillant fait envoyer un formulaire à ta place pendant que tu es connecté.
- **Formulaires Symfony** : jeton automatique (champ `_token`), configuré « stateless » dans `config/packages/csrf.yaml` (vérifie l'origine de la requête), aidé par `assets/controllers/csrf_protection_controller.js`.
- **Petits formulaires manuels** (actions d'échange, retrait, ♥, commentaires) : `csrf_token('id')` dans Twig et `isCsrfTokenValid('id', …)` dans le contrôleur.
- Connexion (`authenticate`) et déconnexion (`logout`) aussi protégées.

### 38. Comment fonctionne la vérification de l'adresse email ?
**Fichiers :** `Service/AccountMailer::sendEmailConfirmation()`, `Controller/VerifyEmailController.php`, `templates/emails/confirm_email.html.twig`, bandeau dans `base.html.twig`.
1. À l'inscription, `verify-email-bundle` génère un **lien signé et daté** (impossible à fabriquer sans la clé secrète).
2. Clic → `VerifyEmailController::verify()` (`/verify/email`) valide la signature puis `setIsVerified(true)`.
3. Tant que ce n'est pas fait : bandeau « Renvoyer le lien » (`/verify/resend`), et **publier / faire une demande / commenter est bloqué** (contrôles `isVerified()` dans `BookController`, `ExchangeController`, `CommentController`).

### 39. Comment fonctionne « mot de passe oublié » ?
**Fichiers :** `Controller/ResetPasswordController.php`, `Entity/ResetPasswordRequest.php`, `Form/ResetPasswordRequestFormType.php`, `Form/ResetPasswordFormType.php`, `config/packages/reset_password.yaml`.
1. `/reset-password` : on saisit son email ; si le compte existe, `reset-password-bundle` crée un jeton (seule son **empreinte** est stockée) et `AccountMailer` envoie le lien.
2. **Même réponse que l'adresse existe ou non** → impossible de savoir qui est inscrit.
3. Clic sur le lien → le jeton est mis en session puis **retiré de l'URL** (pas dans l'historique).
4. Nouveau mot de passe (`NewPasswordType`), lien **à usage unique**, valable **1 h**, une demande toutes les **15 min** (`throttle_limit`).

### 40. Comment fonctionne la page de profil (public et « Mon profil ») ?
- **Public** : `ProfileController::show()` → `/users/{username}`, `UserRepository::findActiveByUsername()` (404 si désactivé/supprimé), annonces en ligne (`findActiveBySeller()`), nombre d'échanges terminés (`countCompletedBySeller()`).
- **Mon profil** : `ProfileController::edit()` (`/profile`) avec **trois formulaires indépendants** : `ProfileType` (pseudo + avatar), `ChangePasswordType` (l'**ancien mot de passe** est redemandé via `UserPassword`), `DeleteAccountType`.
- Avatar : `AccountManager::updateProfile()` → `ImageStorage` (`uploads/avatars`), l'ancien fichier est supprimé du disque.
- Si le pseudo est refusé, `$em->refresh($user)` annule la modification en mémoire (le menu garde l'ancien pseudo).

### 41. Comment fonctionne la suppression de compte (RGPD) ?
`ProfileController::delete()` + `AccountManager::delete()` :
1. Mot de passe redemandé + boîte de confirmation.
2. Échanges en cours : demandes reçues **refusées**, les autres **annulées** (l'annonce de l'autre redevient disponible).
3. Annonces en ligne **retirées**, wishlist désactivée.
4. **Anonymisation** : email `supprime-ID@bookshare.invalid`, pseudo `supprime-ID`, avatar effacé (fichier compris), rôles vidés, mot de passe invalide, `deletedAt` rempli.
5. Déconnexion (`Security::logout(false)`). `UserChecker` bloque toute reconnexion, les commentaires du compte sont masqués.
On ne supprime pas la ligne : les conversations de l'autre personne en dépendent.

### 42. Comment fonctionne l'administration ?
- `src/Controller/Admin/` : `DashboardController` (statistiques + menu) et un `*CrudController` par entité (EasyAdmin).
- Accès : `access_control: ^/admin → ROLE_ADMIN` dans `security.yaml` (sinon 403).
- **Suppression définitive désactivée** pour tous les CRUD (`DashboardController::configureActions()`) : l'admin modère en remplissant `inactivatedAt`.
- Les libellés français des enums s'affichent seuls (Q46), et les règles `#[Assert]` des entités s'appliquent aussi dans les formulaires d'admin.
- Testé dans `tests/Functional/AdminTest.php`.

### 43. Comment fonctionne la wishlist ?
**Fichiers :** `WishlistController.php`, `Service/WishlistManager::toggle()`, `WishlistRepository`, `templates/wishlist/_toggle.html.twig`, `templates/wishlist/index.html.twig`.
- Bouton ♥ (POST + CSRF) sur chaque livre de la page d'une annonce (absent sur ses propres livres).
- `toggle()` : pas de ligne → création ; ligne active → désactivée ; ligne désactivée → **réactivée** (la contrainte d'unicité user+livre interdit d'en créer une deuxième).
- `/wishlist` : `findActiveForUser()` ne montre que les livres **encore en ligne**.
- Cœur plein : `findBookIdsForUser()` renvoie en une seule requête les livres déjà « aimés ».

### 44. Comment fonctionnent les commentaires (questions sous une annonce) ?
`CommentController::add()` (`POST /announcements/{id}/comments`) : connecté (`#[IsGranted]` + `#[CurrentUser]`), email confirmé, jeton CSRF, contenu de 3 à 1000 caractères, puis enregistrement. Affichage : `CommentRepository::findActiveByAnnouncement()` (masque les commentaires modérés et ceux des comptes désactivés ou supprimés). Le vendeur a un badge « Vendeur ».

---

## Partie F — Base de données et Doctrine

### 45. Comment passe-t-on d'une entité PHP à une table MySQL ?
- **Doctrine ORM** : les attributs `#[ORM\Entity]`, `#[ORM\Column]`, `#[ORM\ManyToOne]`… décrivent la table dans la classe.
- Après une modification : `bin/console make:migration` génère un fichier dans `migrations/` (le SQL à exécuter), puis `doctrine:migrations:migrate` l'applique. 3 migrations : création initiale, contraintes d'intégrité, table `reset_password_request`.
- `doctrine:schema:validate` vérifie que les entités et la base correspondent (lancé dans la CI).

### 46. À quoi servent les Enums et comment s'affichent leurs libellés ?
`src/Enum/` : `AnnouncementStatus`, `AnnouncementType`, `BookCondition`, `ExchangeStatus` (enums PHP « backed » stockés en texte en base).
- Chacun a `label()` (« Très bon état »), `color()` (couleur du badge) ou `level()` (jauge d'état), et `ExchangeStatus::isOpen()`.
- `LabelledEnumTrait` + `TranslatableInterface` : les formulaires Symfony (`EnumType`) et EasyAdmin affichent **automatiquement** le libellé français.
- Dans Twig : `book.bookCondition.label`. Une seule source de vérité pour les libellés.

### 47. Comment l'intégrité des données est-elle garantie ?
Trois niveaux :
1. **Base** : clés étrangères, colonnes `NOT NULL` (un message a toujours un échange et un auteur), `UniqueConstraint` (une demande par personne et par annonce, un livre une fois par wishlist), email/pseudo uniques.
2. **Entités** : `#[Assert\…]` et `#[UniqueEntity]`.
3. **Services** : transitions de statut contrôlées (`assertStatus()`), verrou contre la concurrence (Q27).

---

## Partie G — Front-end

### 48. Comment sont gérés le CSS et le JavaScript ?
- **AssetMapper** (`config/packages/asset_mapper.yaml`, `importmap.php`) : pas de Node ni de build ; `{{ importmap('app') }}` dans `base.html.twig` charge `assets/app.js`, qui importe `styles/app.css` et démarre **Stimulus**.
- **Stimulus** : chaque comportement est une petite classe dans `assets/controllers/xxx_controller.js`, branchée dans le HTML avec `data-controller="xxx"` et `data-action="click->xxx#methode"`. **Aucun `<script>` dans les templates.**
- Contrôleurs : `nav` (menu mobile), `reveal` (apparition au défilement), `photo` (image cassée → couverture générée), `gallery`, `chat`, `tabs`, `book-form`, `flash`, `confirm` (boîte `<dialog>`).

### 49. Comment sont réutilisés les morceaux d'interface (badges, couvertures, avatars) ?
- `templates/_partials/ui.html.twig` : **macros Twig** (`ui.cover`, `ui.photo`, `ui.avatar`, `ui.announcement_status`, `ui.price`, `ui.user_link`, `ui.confirm`…), importées avec `{% import '_partials/ui.html.twig' as ui %}`.
- **Includes** : `announcement/_card.html.twig` (carte d'annonce, utilisée à l'accueil, dans la recherche et sur le profil), `wishlist/_toggle.html.twig` (bouton ♥).
- **Héritage** : toutes les pages font `{% extends 'base.html.twig' %}`.
- Photo qui ne charge pas : la couverture générée est dessinée **sous** l'image, et `photo_controller.js` masque l'image en erreur.

---

## Partie H — Tests et qualité

### 50. Comment le projet est-il testé et contrôlé ?
- **143 tests PHPUnit** (`bin/phpunit`), sur une **base SQLite jetable** (`TEST_DATABASE_URL` dans `.env.test`) : la base de dev n'est jamais touchée.
  - `tests/Functional/` : parcours complets dans un faux navigateur (publier, demander, accepter, wishlist, recherche, profil, emails, admin…).
  - `tests/Service/` : règles métier (`ExchangeManager`, notifications).
  - `tests/Security/` : matrices de droits des Voters.
  - `tests/Entity/` : règles de validation des entités.
- **Foundry** (`src/Factory/`) crée les données de chaque test ; `ResetDatabase` repart d'une base vide.
- **PHP-CS-Fixer** (`.php-cs-fixer.dist.php`, règles `@Symfony`) : style uniforme.
- **CI GitHub Actions** (`.github/workflows/ci.yaml`) à chaque push : lint YAML/Twig/conteneur, PHP-CS-Fixer, migrations + `schema:validate`, PHPUnit.
**À retenir :** « chaque fonctionnalité a un test qui la décrit comme un scénario ».

---

## Bonus — Questions pièges probables

- **« Où est le SQL ? »** → Dans les **repositories** via le QueryBuilder de Doctrine (DQL), jamais de SQL concaténé : les valeurs passent par `setParameter()`.
- **« Que se passe-t-il si quelqu'un tape `/exchanges/12` d'un autre ? »** → `ExchangeVoter::VIEW` refuse → **403**.
- **« Et si on envoie un faux statut ou un faux type dans l'URL de recherche ? »** → `tryFrom()` renvoie `null`, le filtre est ignoré.
- **« Pourquoi deux validations (formulaire + entité) ? »** → Le formulaire valide la saisie du site ; l'entité protège la donnée **quel que soit le point d'entrée** (EasyAdmin, fixtures, futur API).
- **« Pourquoi l'email part après le commit ? »** → Pour ne jamais prévenir d'un changement qui aurait été annulé (rollback).
