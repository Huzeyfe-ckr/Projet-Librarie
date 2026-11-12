<?php

namespace App\Tests\Functional;

use App\Entity\Announcement;
use App\Entity\Exchange;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Factory\AnnouncementFactory;
use App\Factory\ExchangeFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * Parcours complet d'un échange depuis le navigateur : demande, accès, actions du vendeur et du demandeur.
 */
final class ExchangeTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;
    private User $seller;
    private User $buyer;
    private Announcement $announcement;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->seller = UserFactory::createOne(['username' => 'vendeur']);
        $this->buyer = UserFactory::createOne(['username' => 'acheteur']);
        $this->announcement = AnnouncementFactory::createOne(['user' => $this->seller, 'title' => 'Dune']);
    }

    public function testUserCanRequestAnAnnouncement(): void
    {
        $this->client->loginUser($this->buyer);
        $crawler = $this->client->request('GET', '/announcements/'.$this->announcement->getId());

        $this->client->submit($crawler->selectButton('Demander ce livre')->form(['message' => 'Bonjour !']));

        $exchange = ExchangeFactory::repository()->findOneBy(['announcement' => $this->announcement, 'requester' => $this->buyer]);
        self::assertNotNull($exchange);
        self::assertSame(ExchangeStatus::Pending, $exchange->getStatus());
        self::assertResponseRedirects('/exchanges/'.$exchange->getId());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.chat', 'Bonjour !');
    }

    public function testSellerCannotRequestHisOwnAnnouncement(): void
    {
        $this->client->loginUser($this->seller);
        $crawler = $this->client->request('GET', '/announcements/'.$this->announcement->getId());

        self::assertCount(0, $crawler->selectButton('Demander ce livre'));
        self::assertSelectorTextContains('.panel', "C'est ton annonce");
    }

    public function testOutsiderCannotOpenTheConversation(): void
    {
        $exchange = $this->createExchange();

        $this->client->loginUser(UserFactory::createOne());
        $this->client->request('GET', '/exchanges/'.$exchange->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testSellerSeesAcceptAndRefuseButtons(): void
    {
        $exchange = $this->createExchange();

        $this->client->loginUser($this->seller);
        $crawler = $this->client->request('GET', '/exchanges/'.$exchange->getId());

        self::assertCount(1, $crawler->selectButton('Accepter la demande'));
        self::assertCount(1, $crawler->selectButton('Refuser'));
    }

    public function testBuyerOnlySeesCancelButton(): void
    {
        $exchange = $this->createExchange();

        $this->client->loginUser($this->buyer);
        $crawler = $this->client->request('GET', '/exchanges/'.$exchange->getId());

        self::assertCount(0, $crawler->selectButton('Accepter la demande'));
        self::assertCount(1, $crawler->selectButton('Annuler ma demande'));
    }

    public function testSellerAcceptsThenCompletesTheExchange(): void
    {
        $exchange = $this->createExchange();
        $this->client->loginUser($this->seller);

        $this->clickAction($exchange, 'Accepter la demande');
        refresh($exchange);
        self::assertSame(ExchangeStatus::Accepted, $exchange->getStatus());
        self::assertSame(AnnouncementStatus::Reserved, $exchange->getAnnouncement()->getStatus());

        $this->clickAction($exchange, 'Livre remis : terminer');
        refresh($exchange);
        self::assertSame(ExchangeStatus::Completed, $exchange->getStatus());
        self::assertNotNull($exchange->getCompletedAt());
        self::assertSame(AnnouncementStatus::Completed, $exchange->getAnnouncement()->getStatus());
    }

    public function testBuyerCannotAcceptHisOwnRequest(): void
    {
        $exchange = $this->createExchange();

        // Les droits sont vérifiés avant le jeton CSRF : même sans jeton valide, l'acheteur reçoit un 403
        $this->client->loginUser($this->buyer);
        $this->client->request('POST', '/exchanges/'.$exchange->getId().'/accept', ['_token' => 'peu-importe']);

        self::assertResponseStatusCodeSame(403);
        refresh($exchange);
        self::assertSame(ExchangeStatus::Pending, $exchange->getStatus());
    }

    public function testClosedExchangeHidesTheMessageForm(): void
    {
        $exchange = $this->createExchange(ExchangeStatus::Refused);

        $this->client->loginUser($this->buyer);
        $this->client->request('GET', '/exchanges/'.$exchange->getId());

        self::assertSelectorNotExists('form.chat-form');
        self::assertSelectorTextContains('.chat-closed', 'Cet échange est clos');
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $exchange = $this->createExchange();
        $this->client->loginUser($this->seller);

        $this->client->request('POST', '/exchanges/'.$exchange->getId().'/accept', ['_token' => 'faux']);

        self::assertResponseRedirects();
        refresh($exchange);
        self::assertSame(ExchangeStatus::Pending, $exchange->getStatus());
    }

    private function createExchange(ExchangeStatus $status = ExchangeStatus::Pending): Exchange
    {
        return ExchangeFactory::createOne([
            'announcement' => $this->announcement,
            'requester' => $this->buyer,
            'status' => $status,
        ]);
    }

    private function clickAction(Exchange $exchange, string $button): void
    {
        $crawler = $this->client->request('GET', '/exchanges/'.$exchange->getId());
        $this->client->submit($crawler->selectButton($button)->form());
        self::assertResponseRedirects();
    }
}
