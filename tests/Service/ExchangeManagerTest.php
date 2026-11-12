<?php

namespace App\Tests\Service;

use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Exception\ExchangeException;
use App\Factory\AnnouncementFactory;
use App\Factory\ExchangeFactory;
use App\Factory\UserFactory;
use App\Service\ExchangeManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Règles métier des échanges : transitions autorisées et interdites.
 */
final class ExchangeManagerTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private ExchangeManager $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->manager = self::getContainer()->get(ExchangeManager::class);
    }

    public function testRequestCreatesPendingExchangeWithDefaultMessage(): void
    {
        $announcement = AnnouncementFactory::createOne(['title' => 'Dune']);
        $buyer = UserFactory::createOne();

        $exchange = $this->manager->request($announcement, $buyer, '   ');

        self::assertSame(ExchangeStatus::Pending, $exchange->getStatus());
        self::assertCount(1, $exchange->getMessages());
        self::assertStringContainsString('« Dune »', $exchange->getMessages()->first()->getContent());
    }

    public function testCannotRequestOwnAnnouncement(): void
    {
        $announcement = AnnouncementFactory::createOne();

        $this->expectException(ExchangeException::class);
        $this->manager->request($announcement, $announcement->getUser(), 'Bonjour');
    }

    public function testCannotRequestUnavailableAnnouncement(): void
    {
        $announcement = AnnouncementFactory::createOne(['status' => AnnouncementStatus::Reserved]);

        $this->expectException(ExchangeException::class);
        $this->manager->request($announcement, UserFactory::createOne(), 'Bonjour');
    }

    public function testAcceptReservesAnnouncementAndRefusesOtherRequests(): void
    {
        $announcement = AnnouncementFactory::createOne();
        $chosen = ExchangeFactory::createOne(['announcement' => $announcement]);
        $other = ExchangeFactory::createOne(['announcement' => $announcement]);
        $alreadyCancelled = ExchangeFactory::createOne(['announcement' => $announcement, 'status' => ExchangeStatus::Cancelled]);
        $announcement->addExchange($chosen)->addExchange($other)->addExchange($alreadyCancelled);

        $this->manager->accept($chosen, $announcement->getUser());

        self::assertSame(ExchangeStatus::Accepted, $chosen->getStatus());
        self::assertSame(AnnouncementStatus::Reserved, $announcement->getStatus());
        self::assertSame(ExchangeStatus::Refused, $other->getStatus());
        self::assertSame(ExchangeStatus::Cancelled, $alreadyCancelled->getStatus(), 'un échange déjà clos ne change pas');
    }

    public function testCompleteClosesExchangeAndAnnouncement(): void
    {
        $exchange = ExchangeFactory::createOne(['status' => ExchangeStatus::Accepted]);
        $exchange->getAnnouncement()->setStatus(AnnouncementStatus::Reserved);

        $this->manager->complete($exchange, $exchange->getAnnouncement()->getUser());

        self::assertSame(ExchangeStatus::Completed, $exchange->getStatus());
        self::assertNotNull($exchange->getCompletedAt());
        self::assertSame(AnnouncementStatus::Completed, $exchange->getAnnouncement()->getStatus());
    }

    public function testCancellingAcceptedExchangeMakesAnnouncementAvailableAgain(): void
    {
        $exchange = ExchangeFactory::createOne(['status' => ExchangeStatus::Accepted]);
        $exchange->getAnnouncement()->setStatus(AnnouncementStatus::Reserved);

        $this->manager->cancel($exchange, $exchange->getRequester());

        self::assertSame(ExchangeStatus::Cancelled, $exchange->getStatus());
        self::assertSame(AnnouncementStatus::Available, $exchange->getAnnouncement()->getStatus());
    }

    public function testCannotCompletePendingExchange(): void
    {
        $exchange = ExchangeFactory::createOne();

        $this->expectException(ExchangeException::class);
        $this->manager->complete($exchange, $exchange->getAnnouncement()->getUser());
    }

    public function testCannotAcceptWhenAnnouncementAlreadyReserved(): void
    {
        $exchange = ExchangeFactory::createOne();
        $exchange->getAnnouncement()->setStatus(AnnouncementStatus::Reserved);

        $this->expectException(ExchangeException::class);
        $this->manager->accept($exchange, $exchange->getAnnouncement()->getUser());
    }

    public function testCannotSendMessageInClosedExchange(): void
    {
        $exchange = ExchangeFactory::createOne(['status' => ExchangeStatus::Refused]);

        $this->expectException(ExchangeException::class);
        $this->manager->sendMessage($exchange, $exchange->getRequester(), 'Encore là ?');
    }
}
