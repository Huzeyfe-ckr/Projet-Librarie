<?php

namespace App\Tests\Functional;

use App\Entity\Announcement;
use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Administration (EasyAdmin) : pas de suppression définitive, le site utilise la suppression douce.
 */
final class AdminTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[DataProvider('crudUrls')]
    public function testListsHaveNoDeleteAction(string $url): void
    {
        $client = self::createClient();
        AnnouncementFactory::createOne();
        $client->loginUser(UserFactory::new()->admin()->create());

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.action-delete, .action-batchDelete'), 'Le bouton « Supprimer » ne doit pas apparaître.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function crudUrls(): iterable
    {
        foreach (['announcement', 'book', 'book-page', 'comment', 'exchange', 'message', 'user', 'wishlist'] as $crud) {
            yield $crud => ['/admin/'.$crud];
        }
    }

    /**
     * Le cas qui provoquait l'erreur 500 (clé étrangère) : une annonce qui a des livres.
     */
    public function testAnnouncementCannotBeDeletedEvenByForgingTheRequest(): void
    {
        $client = self::createClient();
        $announcement = AnnouncementFactory::createOne();
        BookFactory::createOne(['announcement' => $announcement]);
        $id = $announcement->getId();
        $client->loginUser(UserFactory::new()->admin()->create());

        $client->request('POST', '/admin/announcement/'.$id.'/delete');

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull(self::getContainer()->get('doctrine')->getRepository(Announcement::class)->find($id));
    }
}
