<?php

namespace App\Tests\Functional;

use App\Entity\Announcement;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use App\Factory\ExchangeFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

/**
 * Itération 3 : « Mes annonces », modification et retrait d'une annonce.
 */
final class MyAnnouncementsTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;
    private User $owner;
    private Announcement $announcement;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $this->owner = UserFactory::createOne();
        $this->announcement = AnnouncementFactory::new()->donation()->create(['user' => $this->owner, 'title' => 'Pack polars']);
        BookFactory::createOne(['announcement' => $this->announcement, 'title' => 'Mort sur le Nil']);
        BookFactory::createOne(['announcement' => $this->announcement, 'title' => 'Millénium']);
    }

    public function testMyAnnouncementsListsOnlyMine(): void
    {
        AnnouncementFactory::createOne(['title' => 'Annonce de quelqu’un d’autre']);
        ExchangeFactory::createOne(['announcement' => $this->announcement]);

        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/announcements/mine');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Pack polars');
        self::assertSelectorTextContains('main', '1 demande en attente');
        self::assertStringNotContainsString('quelqu’un d’autre', $crawler->filter('main')->text());
    }

    public function testOwnerCanEditTitleAndBooks(): void
    {
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/announcements/'.$this->announcement->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('book_publication[books][0][title]', 'Mort sur le Nil');

        // On renomme l'annonce, on modifie le 1er livre et on retire le 2e
        $token = $crawler->filter('input[name="book_publication[_token]"]')->attr('value');
        $this->client->request('POST', '/announcements/'.$this->announcement->getId().'/edit', ['book_publication' => [
            'title' => 'Agatha Christie',
            'type' => 'donation',
            'books' => [['title' => 'Mort sur le Nil (poche)', 'bookCondition' => 'good']],
            '_token' => $token,
        ]]);

        self::assertResponseRedirects('/announcements/'.$this->announcement->getId());
        refresh($this->announcement);
        self::assertSame('Agatha Christie', $this->announcement->getTitle());
        BookFactory::assert()->exists(['title' => 'Mort sur le Nil (poche)', 'inactivatedAt' => null]);
        self::assertNotNull(BookFactory::repository()->findOneBy(['title' => 'Millénium'])?->getInactivatedAt(), 'le livre retiré est désactivé, pas supprimé');
    }

    public function testOtherUserCannotEdit(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', '/announcements/'.$this->announcement->getId().'/edit');

        self::assertResponseStatusCodeSame(403);
    }

    public function testReservedAnnouncementCannotBeEdited(): void
    {
        $this->announcement->setStatus(AnnouncementStatus::Reserved);
        save($this->announcement);

        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/announcements/'.$this->announcement->getId().'/edit');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeactivateRefusesPendingRequestsAndHidesAnnouncement(): void
    {
        $pending = ExchangeFactory::createOne(['announcement' => $this->announcement]);

        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/announcements/mine');
        $this->client->submit($crawler->selectButton('Retirer')->form());

        self::assertResponseRedirects('/announcements/mine');
        refresh($this->announcement);
        refresh($pending);
        self::assertNotNull($this->announcement->getInactivatedAt());
        self::assertSame(ExchangeStatus::Refused, $pending->getStatus());

        // L'annonce n'est plus visible par les autres
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', '/announcements/'.$this->announcement->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherUserCannotDeactivate(): void
    {
        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('POST', '/announcements/'.$this->announcement->getId().'/deactivate', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
        refresh($this->announcement);
        self::assertNull($this->announcement->getInactivatedAt());
    }
}
