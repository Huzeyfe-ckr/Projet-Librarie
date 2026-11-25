<?php

namespace App\Tests\Service;

use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Factory\AnnouncementFactory;
use App\Factory\ExchangeFactory;
use App\Factory\MessageFactory;
use App\Factory\UserFactory;
use App\Service\ExchangeManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Emails envoyés à l'autre participant d'un échange (ExchangeNotifier, appelé par ExchangeManager).
 */
final class ExchangeNotificationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private ExchangeManager $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->manager = self::getContainer()->get(ExchangeManager::class);
    }

    public function testNewRequestNotifiesSellerWithTheMessage(): void
    {
        $seller = UserFactory::createOne(['email' => 'vendeur@bookshare.test']);
        $announcement = AnnouncementFactory::createOne(['user' => $seller, 'title' => 'Dune']);
        $buyer = UserFactory::createOne(['username' => 'lectrice']);

        $exchange = $this->manager->request($announcement, $buyer, 'Je peux passer samedi ?');

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'to', 'vendeur@bookshare.test');
        self::assertEmailSubjectContains($email, 'Nouvelle demande pour « Dune »');
        self::assertEmailHtmlBodyContains($email, 'lectrice');
        self::assertEmailHtmlBodyContains($email, 'Je peux passer samedi ?');
        self::assertEmailHtmlBodyContains($email, '/exchanges/'.$exchange->getId());
    }

    public function testAcceptNotifiesBuyerAndOtherRequesters(): void
    {
        $announcement = AnnouncementFactory::createOne(['title' => 'Dune']);
        $chosen = ExchangeFactory::createOne(['announcement' => $announcement, 'requester' => UserFactory::new(['email' => 'choisi@bookshare.test'])]);
        ExchangeFactory::createOne(['announcement' => $announcement, 'requester' => UserFactory::new(['email' => 'autre@bookshare.test'])]);

        $this->manager->accept($chosen, $announcement->getUser());

        self::assertEmailCount(2);
        [$accepted, $reservedElsewhere] = self::getMailerMessages();
        self::assertEmailAddressContains($accepted, 'to', 'choisi@bookshare.test');
        self::assertEmailSubjectContains($accepted, 'est acceptée');
        self::assertEmailAddressContains($reservedElsewhere, 'to', 'autre@bookshare.test');
        self::assertEmailSubjectContains($reservedElsewhere, 'réservé par quelqu\'un d\'autre');
    }

    public function testRefuseCompleteAndCancelNotifyTheOtherParticipant(): void
    {
        $refused = ExchangeFactory::createOne(['requester' => UserFactory::new(['email' => 'refuse@bookshare.test'])]);
        $this->manager->refuse($refused, $refused->getAnnouncement()->getUser());
        self::assertEmailAddressContains(self::getMailerMessage(), 'to', 'refuse@bookshare.test');

        $accepted = ExchangeFactory::createOne([
            'status' => ExchangeStatus::Accepted,
            'announcement' => AnnouncementFactory::new(['status' => AnnouncementStatus::Reserved, 'user' => UserFactory::new(['email' => 'vendeur@bookshare.test'])]),
        ]);
        // C'est le demandeur qui annule : c'est donc le vendeur qui est prévenu
        $this->manager->cancel($accepted, $accepted->getRequester());

        self::assertEmailCount(2);
        self::assertEmailAddressContains(self::getMailerMessage(1), 'to', 'vendeur@bookshare.test');
        self::assertEmailSubjectContains(self::getMailerMessage(1), 'Échange annulé');
    }

    public function testCompleteNotifiesBuyer(): void
    {
        $exchange = ExchangeFactory::createOne([
            'status' => ExchangeStatus::Accepted,
            'requester' => UserFactory::new(['email' => 'acheteur@bookshare.test']),
            'announcement' => AnnouncementFactory::new(['status' => AnnouncementStatus::Reserved]),
        ]);

        $this->manager->complete($exchange, $exchange->getAnnouncement()->getUser());

        self::assertEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage(), 'to', 'acheteur@bookshare.test');
        self::assertEmailSubjectContains(self::getMailerMessage(), 'Échange terminé');
    }

    /**
     * Anti-spam : plusieurs messages d'affilée de la même personne ne déclenchent qu'un email.
     */
    public function testOnlyTheFirstMessageOfATurnSendsAnEmail(): void
    {
        $exchange = ExchangeFactory::createOne(['requester' => UserFactory::new(['email' => 'acheteur@bookshare.test'])]);
        $seller = $exchange->getAnnouncement()->getUser();
        $buyer = $exchange->getRequester();
        $exchange->addMessage(MessageFactory::createOne(['exchange' => $exchange, 'sender' => $buyer, 'content' => 'Bonjour !']));

        $this->manager->sendMessage($exchange, $seller, 'Oui, il est dispo.');   // le vendeur prend la parole → email
        $this->manager->sendMessage($exchange, $seller, 'Samedi 14 h ?');        // il continue → pas d'email
        $this->manager->sendMessage($exchange, $buyer, 'Parfait !');            // l'acheteur répond → email

        self::assertEmailCount(2);
        self::assertEmailAddressContains(self::getMailerMessage(0), 'to', 'acheteur@bookshare.test');
        self::assertEmailHtmlBodyContains(self::getMailerMessage(0), 'Oui, il est dispo.');
        self::assertEmailAddressContains(self::getMailerMessage(1), 'to', (string) $seller->getEmail());
    }

    public function testNoEmailToDeletedAccount(): void
    {
        $exchange = ExchangeFactory::createOne(['requester' => UserFactory::new(['deletedAt' => new \DateTimeImmutable()])]);

        $this->manager->refuse($exchange, $exchange->getAnnouncement()->getUser());

        self::assertEmailCount(0);
    }
}
