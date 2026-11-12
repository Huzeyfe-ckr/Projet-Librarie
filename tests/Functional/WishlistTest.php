<?php

namespace App\Tests\Functional;

use App\Entity\Book;
use App\Entity\User;
use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use App\Factory\UserFactory;
use App\Factory\WishlistFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Itération 4 : wishlist (bouton ♥ et page « Ma wishlist »).
 */
final class WishlistTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;
    private User $user;
    private Book $book;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->user = UserFactory::createOne();
        $this->book = BookFactory::createOne(['title' => 'Le Hobbit']);
        $this->client->loginUser($this->user);
    }

    public function testHeartAddsThenRemovesTheBook(): void
    {
        $this->clickHeart();
        self::assertResponseRedirects('/announcements/'.$this->book->getAnnouncement()->getId().'#book-'.$this->book->getId());
        WishlistFactory::assert()->exists(['user' => $this->user, 'book' => $this->book, 'inactivatedAt' => null]);

        $crawler = $this->client->request('GET', '/wishlist');
        self::assertSelectorTextContains('main', 'Le Hobbit');
        self::assertCount(1, $crawler->filter('.wish-item'));

        // 2e clic : retiré (ligne désactivée, pas supprimée), puis 3e clic : réactivée
        $this->clickHeart();
        WishlistFactory::assert()->count(1);
        WishlistFactory::assert()->notExists(['user' => $this->user, 'book' => $this->book, 'inactivatedAt' => null]);
        $this->client->request('GET', '/wishlist');
        self::assertSelectorTextContains('main', 'Ta wishlist est vide');

        $this->clickHeart();
        WishlistFactory::assert()->count(1);
        WishlistFactory::assert()->exists(['user' => $this->user, 'book' => $this->book, 'inactivatedAt' => null]);
    }

    public function testHeartIsFilledWhenBookIsInWishlist(): void
    {
        WishlistFactory::createOne(['user' => $this->user, 'book' => $this->book]);

        $this->client->request('GET', '/announcements/'.$this->book->getAnnouncement()->getId());

        self::assertSelectorExists('#book-'.$this->book->getId().' .wish-toggle__btn.is-active[aria-pressed="true"]');
    }

    public function testOwnerHasNoHeartOnHisOwnBooks(): void
    {
        $mine = BookFactory::createOne(['announcement' => AnnouncementFactory::new(['user' => $this->user])]);

        $this->client->request('GET', '/announcements/'.$mine->getAnnouncement()->getId());

        self::assertSelectorNotExists('.wish-toggle');
    }

    public function testWishlistHidesBooksOfDeactivatedAnnouncements(): void
    {
        $hidden = BookFactory::createOne(['title' => 'Annonce retirée', 'announcement' => AnnouncementFactory::new()->inactive()]);
        WishlistFactory::createOne(['user' => $this->user, 'book' => $hidden]);
        WishlistFactory::createOne(['user' => $this->user, 'book' => $this->book]);

        $crawler = $this->client->request('GET', '/wishlist');

        self::assertCount(1, $crawler->filter('.wish-item'));
        self::assertStringNotContainsString('Annonce retirée', $crawler->filter('main')->text());
    }

    public function testWishlistRequiresLogin(): void
    {
        self::ensureKernelShutdown();
        $anonymous = self::createClient();
        $anonymous->request('GET', '/wishlist');

        self::assertResponseRedirects('/login');
    }

    private function clickHeart(): void
    {
        $crawler = $this->client->request('GET', '/announcements/'.$this->book->getAnnouncement()->getId());
        $this->client->submit($crawler->filter('#book-'.$this->book->getId().' .wish-toggle')->form());
    }
}
