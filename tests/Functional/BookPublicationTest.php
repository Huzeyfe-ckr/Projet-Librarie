<?php

namespace App\Tests\Functional;

use App\Entity\Announcement;
use App\Enum\AnnouncementType;
use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Publication d'une annonce (un livre seul ou un pack).
 */
final class BookPublicationTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Les formulaires utilisent des jetons CSRF « stateless » qui vérifient l'origine de la requête
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $this->client->loginUser(UserFactory::createOne());
    }

    public function testPublishSingleBookUsesBookTitle(): void
    {
        $this->publish([
            'type' => 'sale',
            'price' => '4,50',
            'books' => [['title' => 'Le Petit Prince', 'author' => 'Saint-Exupéry', 'bookCondition' => 'good']],
        ]);

        $announcement = AnnouncementFactory::repository()->findOneBy([]);
        self::assertInstanceOf(Announcement::class, $announcement);
        self::assertResponseRedirects('/announcements/'.$announcement->getId());
        self::assertSame('Le Petit Prince', $announcement->getTitle());
        self::assertSame(AnnouncementType::Sale, $announcement->getType());
        self::assertSame('4.50', $announcement->getPrice());
        BookFactory::assert()->count(1);
    }

    public function testBookPrototypeIsValidHtmlForTheJavascript(): void
    {
        $crawler = $this->client->request('GET', '/books/new');

        // Le contrôleur Stimulus « book-form » insère ce HTML pour ajouter un livre :
        // il doit arriver décodé une seule fois (un double échappement casse le bouton « Ajouter un livre »).
        $prototype = trim((string) $crawler->filter('form[data-controller="book-form"]')->attr('data-book-form-prototype-value'));
        self::assertStringStartsWith('<div class="book-entry"', $prototype);
        self::assertStringContainsString('book_publication[books][__name__][title]', $prototype);
    }

    public function testPublishPack(): void
    {
        $this->publish([
            'title' => 'Pack SF',
            'type' => 'donation',
            'books' => [
                ['title' => 'Dune', 'bookCondition' => 'good'],
                ['title' => 'Fondation', 'bookCondition' => 'very_good'],
            ],
        ]);

        self::assertResponseRedirects();
        AnnouncementFactory::assert()->exists(['title' => 'Pack SF', 'price' => null]);
        BookFactory::assert()->count(2);
    }

    public function testPackWithoutTitleIsRejected(): void
    {
        $this->publish([
            'type' => 'donation',
            'books' => [
                ['title' => 'Dune', 'bookCondition' => 'good'],
                ['title' => 'Fondation', 'bookCondition' => 'good'],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main form', 'Donne un titre à ton pack de livres.');
        AnnouncementFactory::assert()->empty();
    }

    public function testSaleWithoutPriceIsRejected(): void
    {
        $this->publish([
            'type' => 'sale',
            'books' => [['title' => 'Dune', 'bookCondition' => 'good']],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main form', 'Indique un prix pour une vente.');
        AnnouncementFactory::assert()->empty();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function publish(array $data): void
    {
        $crawler = $this->client->request('GET', '/books/new');
        $token = $crawler->filter('input[name="book_publication[_token]"]')->attr('value');

        $this->client->request('POST', '/books/new', ['book_publication' => $data + ['_token' => $token]]);
    }
}
