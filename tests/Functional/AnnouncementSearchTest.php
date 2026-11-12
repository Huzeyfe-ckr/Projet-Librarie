<?php

namespace App\Tests\Functional;

use App\Enum\AnnouncementStatus;
use App\Enum\BookCondition;
use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Itération 6 : recherche dans les livres, filtres et tri de la page Annonces.
 */
final class AnnouncementSearchTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function searches(): iterable
    {
        // [URL, titres attendus dans l'ordre]
        yield 'toutes, plus récentes d\'abord' => ['/announcements', ['Polars', 'Manga', 'Pack SF']];
        yield 'mot présent seulement dans l\'auteur d\'un livre' => ['/announcements?q=Asimov', ['Pack SF']];
        yield 'mot présent dans l\'ISBN' => ['/announcements?q=9782070360536', ['Pack SF']];
        yield 'dons seulement' => ['/announcements?type=donation', ['Pack SF']];
        yield 'genre d\'un des livres' => ['/announcements?genre=Manga', ['Manga']];
        yield 'état d\'un des livres' => ['/announcements?condition=new', ['Manga']];
        yield 'prix max (un don passe toujours)' => ['/announcements?max_price=5', ['Manga', 'Pack SF']];
        yield 'disponibles uniquement' => ['/announcements?available=1', ['Manga', 'Pack SF']];
        yield 'prix croissant (don = 0 €)' => ['/announcements?sort=price_asc', ['Pack SF', 'Manga', 'Polars']];
        yield 'prix décroissant' => ['/announcements?sort=price_desc', ['Polars', 'Manga', 'Pack SF']];
        yield 'filtres combinés' => ['/announcements?type=sale&max_price=5&available=1', ['Manga']];
        yield 'valeurs invalides ignorées' => ['/announcements?type=zzz&max_price=abc&sort=hack', ['Polars', 'Manga', 'Pack SF']];
    }

    #[DataProvider('searches')]
    public function testSearch(string $url, array $expectedTitles): void
    {
        $client = self::createClient();

        $sf = AnnouncementFactory::new()->donation()->create(['title' => 'Pack SF', 'createdAt' => new \DateTimeImmutable('-3 days')]);
        BookFactory::createOne(['announcement' => $sf, 'title' => 'Fondation', 'author' => 'Isaac Asimov', 'isbn' => '9782070360536', 'genre' => 'Science-fiction', 'bookCondition' => BookCondition::Good]);

        $manga = AnnouncementFactory::new()->sale(4.0)->create(['title' => 'Manga', 'createdAt' => new \DateTimeImmutable('-2 days')]);
        BookFactory::createOne(['announcement' => $manga, 'title' => 'One Piece', 'author' => 'Oda', 'genre' => 'Manga', 'bookCondition' => BookCondition::New]);

        $polars = AnnouncementFactory::new()->sale(12.0)->create(['title' => 'Polars', 'status' => AnnouncementStatus::Completed, 'createdAt' => new \DateTimeImmutable('-1 day')]);
        BookFactory::createOne(['announcement' => $polars, 'title' => 'Millénium', 'author' => 'Larsson', 'genre' => 'Policier', 'bookCondition' => BookCondition::Good]);

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedTitles, $this->titles($crawler));
    }

    public function testFiltersAreKeptInTheForm(): void
    {
        $client = self::createClient();
        BookFactory::createOne(['genre' => 'Manga']);

        $client->request('GET', '/announcements?type=sale&genre=Manga&max_price=8&available=1&sort=price_desc');

        self::assertSelectorExists('#f-type option[value="sale"][selected]');
        self::assertSelectorExists('#f-genre option[value="Manga"][selected]');
        self::assertInputValueSame('max_price', '8');
        self::assertSelectorExists('input[name="available"][checked]');
        self::assertSelectorExists('#f-sort option[value="price_desc"][selected]');
    }

    /**
     * @return list<string>
     */
    private function titles(Crawler $crawler): array
    {
        return $crawler->filter('.book-card__title')->each(static fn (Crawler $node): string => trim($node->text()));
    }
}
