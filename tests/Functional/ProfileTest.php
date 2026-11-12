<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\ExchangeStatus;
use App\Factory\AnnouncementFactory;
use App\Factory\ExchangeFactory;
use App\Factory\UserFactory;
use App\Factory\WishlistFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\refresh;

/**
 * Itération 5 : profil public, modification du profil, mot de passe et suppression du compte.
 */
final class ProfileTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $this->user = UserFactory::createOne(['username' => 'alice', 'email' => 'alice@bookswap.test']);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(self::getContainer()->getParameter('app.uploads_dir'));
        parent::tearDown();
    }

    public function testPublicProfileShowsOnlineAnnouncements(): void
    {
        AnnouncementFactory::createOne(['user' => $this->user, 'title' => 'Dune']);
        AnnouncementFactory::new()->inactive()->create(['user' => $this->user, 'title' => 'Retirée']);
        AnnouncementFactory::createOne(['user' => $this->user, 'status' => AnnouncementStatus::Completed]);

        $crawler = $this->client->request('GET', '/users/alice');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'alice');
        self::assertSelectorTextContains('.profile-head', '1 échange terminé');
        self::assertStringContainsString('Dune', $crawler->filter('main')->text());
        self::assertStringNotContainsString('Retirée', $crawler->filter('main')->text());
    }

    public function testProfileOfInactiveOrUnknownUserIs404(): void
    {
        UserFactory::new()->inactive()->create(['username' => 'oceane']);

        $this->client->request('GET', '/users/oceane');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/users/personne');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateUsernameAndAvatar(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/profile');

        $form = $crawler->selectButton('Enregistrer')->form(['profile[username]' => 'alice_b']);
        $form['profile[avatar]']->upload($this->png());
        $this->client->submit($form);

        self::assertResponseRedirects('/profile');
        refresh($this->user);
        self::assertSame('alice_b', $this->user->getUsername());
        self::assertMatchesRegularExpression('#^/uploads/avatars/[0-9a-f]{24}\.png$#', (string) $this->user->getAvatarUrl());
    }

    public function testUsernameRulesAreChecked(): void
    {
        UserFactory::createOne(['username' => 'bob']);
        $this->client->loginUser($this->user);

        foreach (['bob' => 'déjà pris', 'élodie' => 'lettres sans accent', 'ab' => 'au moins 3'] as $username => $error) {
            $crawler = $this->client->request('GET', '/profile');
            $this->client->submit($crawler->selectButton('Enregistrer')->form(['profile[username]' => $username]));

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('main', $error);
            // Le menu affiche toujours l'ancien pseudo
            self::assertSelectorTextContains('.nav__user', 'alice');
        }

        refresh($this->user);
        self::assertSame('alice', $this->user->getUsername());
    }

    public function testChangePasswordRequiresCurrentPassword(): void
    {
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', '/profile');
        $this->client->submit($crawler->selectButton('Changer le mot de passe')->form([
            'change_password[currentPassword]' => 'mauvais',
            'change_password[newPassword][first]' => 'Un-Livre-Ouvert-2026!',
            'change_password[newPassword][second]' => 'Un-Livre-Ouvert-2026!',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Mot de passe actuel incorrect.');

        $crawler = $this->client->request('GET', '/profile');
        $this->client->submit($crawler->selectButton('Changer le mot de passe')->form([
            'change_password[currentPassword]' => 'password',
            'change_password[newPassword][first]' => 'Un-Livre-Ouvert-2026!',
            'change_password[newPassword][second]' => 'Un-Livre-Ouvert-2026!',
        ]));
        self::assertResponseRedirects('/profile');

        refresh($this->user);
        $hasher = self::getContainer()->get('security.user_password_hasher');
        self::assertTrue($hasher->isPasswordValid($this->user, 'Un-Livre-Ouvert-2026!'));
    }

    public function testDeleteAccountAnonymisesAndCleansUp(): void
    {
        $myAnnouncement = AnnouncementFactory::createOne(['user' => $this->user]);
        $receivedRequest = ExchangeFactory::createOne(['announcement' => $myAnnouncement]);
        $sentRequest = ExchangeFactory::createOne(['requester' => $this->user, 'status' => ExchangeStatus::Accepted]);
        $sentRequest->getAnnouncement()->setStatus(AnnouncementStatus::Reserved);
        WishlistFactory::createOne(['user' => $this->user]);
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', '/profile');
        $this->client->submit($crawler->selectButton('Supprimer mon compte')->form(['delete_account[currentPassword]' => 'password']));

        self::assertResponseRedirects('/');
        refresh($this->user);
        self::assertNotNull($this->user->getDeletedAt());
        self::assertSame('supprime-'.$this->user->getId(), $this->user->getUsername());
        self::assertStringEndsWith('@bookshare.invalid', $this->user->getEmail());

        refresh($myAnnouncement);
        refresh($receivedRequest);
        refresh($sentRequest);
        self::assertNotNull($myAnnouncement->getInactivatedAt(), 'annonce retirée');
        self::assertSame(ExchangeStatus::Refused, $receivedRequest->getStatus(), 'demande reçue refusée');
        self::assertSame(ExchangeStatus::Cancelled, $sentRequest->getStatus(), 'échange en cours annulé');
        self::assertSame(AnnouncementStatus::Available, $sentRequest->getAnnouncement()->getStatus(), 'l\'annonce de l\'autre redevient disponible');
        WishlistFactory::assert()->notExists(['user' => $this->user, 'inactivatedAt' => null]);

        // Déconnecté, et plus possible de se reconnecter
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
    }

    public function testDeleteAccountWithWrongPasswordDoesNothing(): void
    {
        $this->client->loginUser($this->user);

        $crawler = $this->client->request('GET', '/profile');
        $this->client->submit($crawler->selectButton('Supprimer mon compte')->form(['delete_account[currentPassword]' => 'mauvais']));

        self::assertResponseRedirects('/profile#delete');
        refresh($this->user);
        self::assertNull($this->user->getDeletedAt());
    }

    private function png(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, base64_decode(self::TINY_PNG));

        return new UploadedFile($path, 'moi.png', 'image/png', null, true);
    }
}
