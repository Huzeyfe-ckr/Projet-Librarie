<?php

namespace App\Story;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\User;
use App\Enum\AnnouncementType;
use App\Enum\BookCondition;
use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use App\Factory\BookPageFactory;
use App\Factory\CommentFactory;
use App\Factory\UserFactory;
use App\Factory\WishlistFactory;
use App\Service\ExchangeManager;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

use function Zenstruck\Foundry\faker;

/**
 * Jeu de données de développement.
 *
 *   docker compose exec php bin/console foundry:load-fixtures main
 *
 * Tous les comptes ont le mot de passe « password » : admin@bookswap.test (ROLE_ADMIN), alice@bookswap.test, bob@bookswap.test…
 *
 * Les échanges passent par ExchangeManager : les statuts des annonces et des échanges
 * sont donc toujours cohérents avec les vraies règles de l'application.
 */
#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    private const USERNAMES = ['alice', 'bob', 'chloe', 'david', 'emma', 'farid', 'gaelle', 'hugo', 'ines', 'julien',
        'karima', 'lucas', 'manon', 'nathan', 'oceane', 'paul', 'sarah', 'thomas', 'yasmine'];

    /** [titre, auteur, isbn, genre] */
    private const CATALOGUE = [
        ["Harry Potter à l'école des sorciers", 'J.K. Rowling', '9782070584628', 'Fantasy'],
        ['Harry Potter et la Chambre des secrets', 'J.K. Rowling', '9782070584642', 'Fantasy'],
        ["Harry Potter et le Prisonnier d'Azkaban", 'J.K. Rowling', '9782070584925', 'Fantasy'],
        ['Le Seigneur des anneaux - La Communauté', 'J.R.R. Tolkien', '9782266282369', 'Fantasy'],
        ['Le Hobbit', 'J.R.R. Tolkien', '9782253049418', 'Fantasy'],
        ['Les Misérables', 'Victor Hugo', '9782253096337', 'Classique'],
        ['Notre-Dame de Paris', 'Victor Hugo', '9782253009689', 'Classique'],
        ['Madame Bovary', 'Gustave Flaubert', '9782253004868', 'Classique'],
        ["L'Étranger", 'Albert Camus', '9782070360024', 'Classique'],
        ['La Peste', 'Albert Camus', '9782070360420', 'Classique'],
        ['Germinal', 'Émile Zola', '9782253004226', 'Classique'],
        ['Le Comte de Monte-Cristo', 'Alexandre Dumas', '9782253098058', 'Aventure'],
        ['Les Trois Mousquetaires', 'Alexandre Dumas', '9782253008880', 'Aventure'],
        ['Fondation', 'Isaac Asimov', '9782070360536', 'Science-fiction'],
        ['Dune', 'Frank Herbert', '9782266320481', 'Science-fiction'],
        ['Ubik', 'Philip K. Dick', '9782264034922', 'Science-fiction'],
        ['1984', 'George Orwell', '9782070368228', 'Science-fiction'],
        ['Le Meilleur des mondes', 'Aldous Huxley', '9782266283038', 'Science-fiction'],
        ['Fahrenheit 451', 'Ray Bradbury', '9782070415731', 'Science-fiction'],
        ['One Piece - Tome 1', 'Eiichiro Oda', '9782723434621', 'Manga'],
        ['One Piece - Tome 2', 'Eiichiro Oda', '9782723434638', 'Manga'],
        ["L'Attaque des Titans - Tome 1", 'Hajime Isayama', '9782811611699', 'Manga'],
        ['Naruto - Tome 1', 'Masashi Kishimoto', '9782871294146', 'Manga'],
        ['Le Petit Prince', 'Antoine de Saint-Exupéry', '9782070612758', 'Jeunesse'],
        ['Le Petit Nicolas', 'Sempé et Goscinny', '9782070612765', 'Jeunesse'],
        ['Astérix le Gaulois', 'Goscinny et Uderzo', '9782012101333', 'Bande dessinée'],
        ['Tintin au Tibet', 'Hergé', '9782203001190', 'Bande dessinée'],
        ['Sapiens', 'Yuval Noah Harari', '9782226257017', 'Essai'],
        ["L'Art de la guerre", 'Sun Tzu', '9782081304536', 'Essai'],
        ["Le Crime de l'Orient-Express", 'Agatha Christie', '9782253010647', 'Policier'],
        ['Mort sur le Nil', 'Agatha Christie', '9782253012528', 'Policier'],
        ['Millénium - Tome 1', 'Stieg Larsson', '9782742761579', 'Policier'],
        ["La Vérité sur l'affaire Harry Quebert", 'Joël Dicker', '9782877068161', 'Policier'],
        ['CSS maintenables', 'Kaelig Deloumeau-Prigent', '9782212145427', 'Informatique'],
        ['Design émotionnel', 'Aarron Walter', '9782212138771', 'Design'],
        ['Clean Code', 'Robert C. Martin', '9780132350884', 'Informatique'],
    ];

    /** [titre de l'annonce, description, type, prix, index des livres du catalogue] */
    private const ANNOUNCEMENTS = [
        ['Pack Harry Potter tomes 1 à 3', 'Les trois premiers tomes, couvertures un peu usées mais complets.', AnnouncementType::Sale, 15.0, [0, 1, 2]],
        ['Tolkien : Hobbit + Seigneur des anneaux', 'Deux classiques de la fantasy, format poche.', AnnouncementType::Sale, 10.0, [3, 4]],
        ['Victor Hugo à donner', 'Je fais du tri dans ma bibliothèque.', AnnouncementType::Donation, null, [5, 6]],
        ['Classiques du lycée', 'Lus pour le bac, quelques annotations au crayon.', AnnouncementType::Donation, null, [7, 8, 9]],
        ['Germinal', 'Édition Livre de Poche.', AnnouncementType::Sale, 3.0, [10]],
        ['Pack Alexandre Dumas', 'Monte-Cristo et les Mousquetaires, parfaits pour les vacances.', AnnouncementType::Sale, 8.5, [11, 12]],
        ['SF incontournables', 'Asimov, Herbert, Dick : de quoi bien démarrer en science-fiction.', AnnouncementType::Sale, 12.0, [13, 14, 15]],
        ['Dystopies', '1984, Le Meilleur des mondes et Fahrenheit 451.', AnnouncementType::Donation, null, [16, 17, 18]],
        ['One Piece tomes 1 et 2', 'Quasiment neufs.', AnnouncementType::Sale, 7.0, [19, 20]],
        ["L'Attaque des Titans T1", 'Lu une fois.', AnnouncementType::Sale, 4.0, [21]],
        ['Naruto T1', 'Couverture légèrement abîmée.', AnnouncementType::Donation, null, [22]],
        ['Livres jeunesse', 'Pour les enfants de 7 à 12 ans.', AnnouncementType::Donation, null, [23, 24]],
        ['BD Astérix et Tintin', 'Albums cartonnés.', AnnouncementType::Sale, 9.0, [25, 26]],
        ['Essais', "Sapiens et L'Art de la guerre.", AnnouncementType::Sale, 11.0, [27, 28]],
        ['Agatha Christie x2', "Deux enquêtes d'Hercule Poirot.", AnnouncementType::Sale, 5.0, [29, 30]],
        ['Polars modernes', 'Millénium et Harry Quebert, très bons page-turners.', AnnouncementType::Sale, 9.5, [31, 32]],
        ['Livres de cours MMI', 'Dev web et design, utilisés en BUT MMI.', AnnouncementType::Sale, 25.0, [33, 34, 35]],
    ];

    private const QUESTIONS = ['Est-ce que le prix est négociable ?', 'Toujours disponible ?', 'Remise en main propre possible ?',
        'Les pages sont-elles annotées ?', 'Super annonce, merci !', "C'est quelle édition exactement ?"];
    private const CHAT = ['Samedi matin ça vous irait ?', "Parfait, rendez-vous devant l'IUT.", 'Je peux passer après 18h.',
        'Merci beaucoup !', "D'accord, à bientôt."];

    public function __construct(
        private readonly ExchangeManager $exchangeManager,
    ) {
    }

    public function build(): void
    {
        UserFactory::new()->admin()->create(['username' => 'admin', 'email' => 'admin@bookswap.test']);

        /** @var User[] $users */
        $users = [];
        foreach (self::USERNAMES as $name) {
            $users[] = UserFactory::createOne([
                'username' => $name,
                'email' => $name.'@bookswap.test',
                // deux comptes non vérifiés et un compte désactivé pour tester ces cas
                'isVerified' => !\in_array($name, ['nathan', 'paul'], true),
                'inactivatedAt' => 'oceane' === $name ? new \DateTimeImmutable('-3 days') : null,
            ]);
        }
        $activeUsers = array_values(array_filter($users, static fn (User $u): bool => null === $u->getInactivatedAt()));

        $books = [];
        foreach (self::ANNOUNCEMENTS as [$title, $description, $type, $price, $bookIndexes]) {
            $announcement = $this->createAnnouncement(faker()->randomElement($activeUsers), $title, $description, $type, $price);

            foreach ($bookIndexes as $index) {
                [$bookTitle, $author, $isbn, $genre] = self::CATALOGUE[$index];
                $book = BookFactory::createOne([
                    'announcement' => $announcement,
                    'title' => $bookTitle,
                    'author' => $author,
                    'isbn' => $isbn,
                    'genre' => $genre,
                    'bookCondition' => faker()->randomElement(BookCondition::cases()),
                ]);
                BookPageFactory::createOne(['book' => $book]);
                $books[] = $book;
            }

            $this->addComments($announcement, $activeUsers);
            $this->addExchanges($announcement, $activeUsers);
        }

        $this->addWishlists($activeUsers, $books);
    }

    private function createAnnouncement(User $seller, string $title, string $description, AnnouncementType $type, ?float $price): Announcement
    {
        $factory = AnnouncementFactory::new(['user' => $seller, 'title' => $title, 'description' => $description]);
        $factory = AnnouncementType::Sale === $type ? $factory->sale((float) $price) : $factory->donation();

        return $factory->create();
    }

    /**
     * @param User[] $users
     */
    private function addComments(Announcement $announcement, array $users): void
    {
        $others = $this->othersThan($announcement->getUser(), $users);

        foreach (faker()->randomElements($others, faker()->numberBetween(0, 3)) as $author) {
            CommentFactory::createOne(['announcement' => $announcement, 'user' => $author, 'content' => faker()->randomElement(self::QUESTIONS)]);
        }
    }

    /**
     * Crée 0 à 2 demandes par annonce et les fait avancer dans le workflow.
     *
     * @param User[] $users
     */
    private function addExchanges(Announcement $announcement, array $users): void
    {
        $owner = $announcement->getUser();
        $requesters = faker()->randomElements($this->othersThan($owner, $users), faker()->numberBetween(0, 2));

        foreach ($requesters as $requester) {
            $exchange = $this->exchangeManager->request($announcement, $requester, '');
            $this->exchangeManager->sendMessage($exchange, $owner, 'Bonjour, oui il est toujours disponible !');

            // Le premier demandeur a une chance d'être accepté ; le suivant est alors refusé automatiquement.
            match (faker()->numberBetween(1, 5)) {
                1 => $this->exchangeManager->refuse($exchange, $owner),
                2, 3 => $this->acceptAndMaybeFinish($exchange, $owner, $requester),
                default => null, // reste en attente
            };

            if ('available' !== $announcement->getStatus()->value) {
                break;
            }
        }
    }

    private function acceptAndMaybeFinish(\App\Entity\Exchange $exchange, User $owner, User $requester): void
    {
        $this->exchangeManager->accept($exchange, $owner);
        $this->exchangeManager->sendMessage($exchange, $requester, faker()->randomElement(self::CHAT));

        match (faker()->numberBetween(1, 3)) {
            1 => $this->exchangeManager->complete($exchange, $owner),
            2 => $this->exchangeManager->cancel($exchange, $requester),
            default => null, // reste accepté (annonce réservée)
        };
    }

    /**
     * @param User[] $users
     * @param Book[] $books
     */
    private function addWishlists(array $users, array $books): void
    {
        $taken = [];
        foreach ($users as $user) {
            foreach (faker()->randomElements($books, 2) as $book) {
                $key = $user->getId().'-'.$book->getId();
                if ($book->getAnnouncement()->getUser() === $user || isset($taken[$key])) {
                    continue;
                }
                $taken[$key] = true;
                WishlistFactory::createOne(['user' => $user, 'book' => $book]);
            }
        }
    }

    /**
     * @param User[] $users
     *
     * @return User[]
     */
    private function othersThan(User $user, array $users): array
    {
        return array_values(array_filter($users, static fn (User $u): bool => $u !== $user));
    }
}
