<?php

namespace App\Tests\Functional;

use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Pages publiques et règles de visibilité des annonces.
 */
final class PublicPagesTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[DataProvider('publicUrls')]
    public function testPublicPagesAreReachable(string $url): void
    {
        $client = self::createClient();
        AnnouncementFactory::createMany(3);

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicUrls(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'annonces' => ['/announcements'];
        yield 'connexion' => ['/login'];
        yield 'inscription' => ['/register'];
    }

    public function testAnnouncementPageShowsItsBooks(): void
    {
        $client = self::createClient();
        $announcement = AnnouncementFactory::createOne(['title' => 'Pack Tolkien']);
        BookFactory::createOne(['announcement' => $announcement, 'title' => 'Le Hobbit']);

        $client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pack Tolkien');
        self::assertSelectorTextContains('.book-list', 'Le Hobbit');
    }

    public function testSearchFindsAnnouncementByTitle(): void
    {
        $client = self::createClient();
        AnnouncementFactory::createOne(['title' => 'Dune et Fondation']);
        AnnouncementFactory::createOne(['title' => 'Astérix le Gaulois']);

        $crawler = $client->request('GET', '/announcements?q=Dune');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Dune et Fondation', $crawler->filter('main')->text());
        self::assertStringNotContainsString('Astérix', $crawler->filter('main')->text());
    }

    public function testInactiveAnnouncementIsHiddenFromUsers(): void
    {
        $client = self::createClient();
        $announcement = AnnouncementFactory::new()->inactive()->create();

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminCanStillSeeInactiveAnnouncement(): void
    {
        $client = self::createClient();
        $announcement = AnnouncementFactory::new()->inactive()->create();

        $client->loginUser(UserFactory::new()->admin()->create());
        $client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash--warning', 'Annonce désactivée');
    }

    public function testAnnouncementOfInactiveUserIsHidden(): void
    {
        $client = self::createClient();
        $announcement = AnnouncementFactory::createOne(['user' => UserFactory::new()->inactive()]);

        $client->request('GET', '/announcements/'.$announcement->getId());

        self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('privateUrls')]
    public function testPrivatePagesRedirectAnonymousToLogin(string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $url);

        self::assertResponseRedirects('/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function privateUrls(): iterable
    {
        yield 'publier' => ['/books/new'];
        yield 'mes échanges' => ['/exchanges'];
    }

    public function testAdminAreaIsForbiddenToRegularUsers(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }
}
