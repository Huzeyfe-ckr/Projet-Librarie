# Projet Symfony (Docker) : journal de mise en place

Ce document résume, dans l'ordre, tout ce qui a été fait après la création des entités : base de données, inscription, EasyAdmin, toolbar. Il contient aussi les erreurs rencontrées et leur correction.

## Environnement

- Projet basé sur `symfony-docker` (PHP tourne dans un conteneur Docker)
- Base de données : MySQL / MariaDB (les erreurs SQL `1146` et `1048` le confirment)
- EasyAdmin en version 5 (`MenuItem::linkToCrud()` n'existe plus, on utilise `MenuItem::linkTo()`)
- Entités : `User`, `Announcement`, `Book`, `BookPage`, `Exchange`, `Wishlist`, `Message`, `Comment`

## 0. Où lancer les commandes

PHP n'est pas installé sur le Mac (`zsh: command not found: php`). Deux options :

```bash
# Depuis le Mac, dans le dossier symfony-docker
docker compose up -d --wait
docker compose exec php php bin/console <commande>
docker compose exec php composer <commande>

# Ou directement dans le conteneur (prompt root@...:/app#)
docker compose exec php sh
php bin/console <commande>
```

Alias pratiques (à mettre dans `~/.zshrc`) :

```bash
alias sf='docker compose exec php php bin/console'
alias dcomposer='docker compose exec php composer'
```

Le site est accessible sur `https://localhost`.

## 1. Base de données et migrations

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console make:migration
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

`schema:validate` doit afficher `[OK]` pour Mapping et Database.

### Erreur : `Table 'app.user' doesn't exist`

Les migrations n'avaient pas été jouées. Solution : relancer `make:migration` puis `migrate`.

Si `make:migration` dit « no changes » alors que les tables n'existent pas (dev uniquement, efface les données) :

```bash
php bin/console doctrine:schema:drop --force --full-database
php bin/console doctrine:migrations:version --delete --all --no-interaction
rm migrations/Version*.php
php bin/console make:migration
php bin/console doctrine:migrations:migrate --no-interaction
```

## 2. Correction des `UniqueConstraint`

### Erreur

```
Attribute "Doctrine\ORM\Mapping\UniqueConstraint" cannot target property (allowed targets: class)
```

`#[ORM\UniqueConstraint]` était placé au-dessus d'une propriété dans `Wishlist`, `Exchange` et `BookPage`. Cet attribut doit être au niveau de la **classe**.

```php
#[ORM\Entity(repositoryClass: WishlistRepository::class)]
#[ORM\UniqueConstraint(columns: ['user_id', 'book_id'])]
class Wishlist
```

Contraintes corrigées :

| Entité | Colonnes |
|---|---|
| `Wishlist` | `user_id`, `book_id` |
| `Exchange` | `announcement_id`, `requester_id` |
| `BookPage` | `book_id`, `page_order` |

Vérification : `grep -rn -B1 -A1 "UniqueConstraint" src/Entity/`

## 3. Authentification et inscription

```bash
php bin/console make:user
php bin/console make:auth
php bin/console make:registration-form
```

### Entité `User`

- `email` : `unique: true`
- `username` : `unique: true`
- `UniqueEntity` sur `email` et `username` pour afficher une erreur dans le formulaire au lieu d'une exception SQL :

```php
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà pris.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
```

### Erreur : `Column 'username' cannot be null`

Le formulaire généré ne gérait que l'email et le mot de passe. Il a fallu ajouter le champ `username` :

1. Dans `src/Form/RegistrationFormType.php` : champ `username` (`TextType`, contraintes `NotBlank` et `Length`).
2. Dans `templates/registration/register.html.twig` : `{{ form_row(registrationForm.username) }}`.
3. Dans `RegistrationController`, avant le `persist` : `$user->setCguValidatedAt(new \DateTimeImmutable());`

### Erreur : `Could not load type "App\Form\TextType"`

Il manquait l'import dans `RegistrationFormType.php` :

```php
use Symfony\Component\Form\Extension\Core\Type\TextType;
```

Page d'inscription : `https://localhost/register`

## 4. Donner le rôle admin à son compte

Erreur : `Access Denied. The user doesn't have ROLE_ADMIN.`

```bash
php bin/console dbal:run-sql "UPDATE user SET roles = JSON_ARRAY('ROLE_ADMIN') WHERE email = 'ton@email.com'"
php bin/console dbal:run-sql "SELECT id, email, roles FROM user"
```

Ensuite, se déconnecter (`https://localhost/logout`) puis se reconnecter, car les rôles sont chargés à la connexion.

## 5. Symfony Profiler (toolbar)

```bash
composer require --dev symfony/profiler-pack
php bin/console cache:clear
```

La barre apparaît en bas des pages en environnement `dev`, à condition que `base.html.twig` contienne `</body>`. Le profiler complet est sur `https://localhost/_profiler`.

## 6. EasyAdmin

### 6.1 Dashboard

```bash
php bin/console make:admin:dashboard
```

Garder les valeurs par défaut (`DashboardController`, dossier `src/Controller/Admin/`). Le fichier créé par erreur sous un autre nom (`adminController.php`) a été supprimé.

### 6.2 Un CRUD par entité

```bash
php bin/console make:admin:crud
```

Commande interactive : taper le **numéro** de l'entité, puis Entrée. À répéter pour les 8 entités :

| N° | Entité |
|---|---|
| 0 | Announcement |
| 1 | Book |
| 2 | BookPage |
| 3 | Comment |
| 4 | Exchange |
| 5 | Message |
| 6 | User |
| 7 | Wishlist |

Vérification : `ls src/Controller/Admin/` doit montrer 8 `XxxCrudController` et le `DashboardController`.

### 6.3 Remplacer la page « Welcome to EasyAdmin »

Cette page vient de `return parent::index();` dans le `DashboardController` généré. Elle est remplacée par un vrai tableau de bord avec des statistiques.

`src/Controller/Admin/DashboardController.php` :

```php
<?php

namespace App\Controller\Admin;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\Comment;
use App\Entity\Exchange;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'Utilisateurs' => $this->em->getRepository(User::class)->count([]),
                'Annonces' => $this->em->getRepository(Announcement::class)->count([]),
                'Livres' => $this->em->getRepository(Book::class)->count([]),
                'Échanges' => $this->em->getRepository(Exchange::class)->count([]),
                'Messages' => $this->em->getRepository(Message::class)->count([]),
                'Commentaires' => $this->em->getRepository(Comment::class)->count([]),
            ],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Administration');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(BookCrudController::class, 'Livres', 'fa fa-book');
        yield MenuItem::linkTo(BookPageCrudController::class, 'Pages de livres', 'fa fa-file-lines');
        yield MenuItem::linkTo(AnnouncementCrudController::class, 'Annonces', 'fa fa-bullhorn');

        yield MenuItem::section('Activité');
        yield MenuItem::linkTo(ExchangeCrudController::class, 'Échanges', 'fa fa-right-left');
        yield MenuItem::linkTo(MessageCrudController::class, 'Messages', 'fa fa-envelope');
        yield MenuItem::linkTo(CommentCrudController::class, 'Commentaires', 'fa fa-comments');
        yield MenuItem::linkTo(WishlistCrudController::class, 'Wishlists', 'fa fa-heart');

        yield MenuItem::section('Site');
        yield MenuItem::linkToUrl('Retour au site', 'fa fa-arrow-left', '/');
    }
}
```

`templates/admin/dashboard.html.twig` :

```twig
{% extends '@EasyAdmin/page/content.html.twig' %}

{% block page_title %}Tableau de bord{% endblock %}

{% block main %}
    <div class="row">
        {% for label, total in stats %}
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="h1 mb-1">{{ total }}</div>
                        <div class="text-muted">{{ label }}</div>
                    </div>
                </div>
            </div>
        {% endfor %}
    </div>
{% endblock %}
```

Puis : `php bin/console cache:clear`

### Erreurs rencontrées

| Erreur | Cause | Solution |
|---|---|---|
| `ParseError: unexpected token "public"` | Constructeur et `index()` collés avant `class` | Tout doit être à l'intérieur de la classe : remplacer le fichier complet |
| `Call to undefined method MenuItem::linkToCrud()` | API EasyAdmin 4 utilisée avec la v5 | Utiliser `MenuItem::linkTo(XxxCrudController::class, ...)` |

## 7. Commandes utiles au quotidien

```bash
php bin/console cache:clear              # vider le cache
php bin/console debug:router             # lister les routes
php bin/console doctrine:schema:validate # vérifier mapping et base
php bin/console about                    # version, environnement
```

## 8. Prochaines étapes

1. Personnaliser les champs de chaque `XxxCrudController` (`configureFields()`), par exemple un `ChoiceField` pour les rôles dans `UserCrudController`.
2. Créer les pages publiques : contrôleurs, formulaires et templates Twig (annonces, livres, échanges, wishlist).
3. Protéger les pages avec `#[IsGranted('ROLE_USER')]` ou `access_control` dans `security.yaml`.
4. Ajouter des données de test avec `orm-fixtures` et `fakerphp/faker`.
5. Ajouter la vérification d'email à l'inscription (`symfonycasts/verify-email-bundle`).
