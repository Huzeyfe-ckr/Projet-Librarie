<?php

namespace App\Tests\Functional;

use App\Entity\BookPage;
use App\Entity\User;
use App\Factory\AnnouncementFactory;
use App\Factory\BookFactory;
use App\Factory\BookPageFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * Itération 2 : photos des livres (envoi, affichage, suppression).
 */
final class BookPhotoTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /** PNG valide de 1×1 pixel */
    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $this->user = UserFactory::createOne();
        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        // Les photos envoyées pendant les tests sont supprimées (dossier var/test-uploads)
        (new Filesystem())->remove(self::getContainer()->getParameter('app.uploads_dir'));
        parent::tearDown();
    }

    public function testPublishBookWithPhotos(): void
    {
        $this->publish([$this->png('couverture.png'), $this->png('dos.png')]);

        self::assertResponseRedirects();
        /** @var BookPage[] $pages */
        $pages = BookPageFactory::repository()->findBy([], ['pageOrder' => 'ASC']);
        self::assertCount(2, $pages);
        self::assertSame([1, 2], [$pages[0]->getPageOrder(), $pages[1]->getPageOrder()]);
        self::assertMatchesRegularExpression('#^/uploads/books/[0-9a-f]{24}\.png$#', $pages[0]->getImgUrl(), 'nom de fichier aléatoire, jamais celui envoyé');
        self::assertFileExists(self::getContainer()->getParameter('app.uploads_dir').'/books/'.basename($pages[0]->getImgUrl()));

        // La photo remplace la couverture générée sur la page de l'annonce
        $this->client->followRedirect();
        self::assertSelectorExists('img.gallery__main[src="'.$pages[0]->getImgUrl().'"]');
        self::assertSelectorCount(2, '.gallery__thumbs button');
    }

    public function testNonImageFileIsRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, 'je ne suis pas une image');

        $this->publish([new UploadedFile($path, 'virus.jpg', 'image/jpeg', null, true)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main form', 'Envoie une image JPEG, PNG ou WebP.');
        BookPageFactory::assert()->empty();
    }

    public function testOwnerCanDeleteAPhoto(): void
    {
        $announcement = AnnouncementFactory::new()->donation()->create(['user' => $this->user]);
        $book = BookFactory::createOne(['announcement' => $announcement]);
        $page = BookPageFactory::createOne(['book' => $book]);

        $crawler = $this->client->request('GET', '/announcements/'.$announcement->getId().'/edit');
        $button = $crawler->filter('button[formaction="/books/photos/'.$page->getId().'/delete"]');
        self::assertCount(1, $button);

        $this->client->request('POST', '/books/photos/'.$page->getId().'/delete', ['_token' => $button->attr('value')]);

        self::assertResponseRedirects('/announcements/'.$announcement->getId().'/edit');
        refresh($page);
        self::assertNotNull($page->getInactivatedAt());
    }

    public function testOtherUserCannotDeletePhoto(): void
    {
        $page = BookPageFactory::createOne();

        $this->client->request('POST', '/books/photos/'.$page->getId().'/delete', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param UploadedFile[] $photos
     */
    private function publish(array $photos): void
    {
        $crawler = $this->client->request('GET', '/books/new');
        $token = $crawler->filter('input[name="book_publication[_token]"]')->attr('value');

        $this->client->request(
            'POST',
            '/books/new',
            ['book_publication' => [
                'type' => 'donation',
                'books' => [['title' => 'Le Hobbit', 'bookCondition' => 'good']],
                '_token' => $token,
            ]],
            ['book_publication' => ['books' => [['photos' => $photos]]]],
        );
    }

    private function png(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, base64_decode(self::TINY_PNG));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
