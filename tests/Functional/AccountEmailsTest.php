<?php

namespace App\Tests\Functional;

use App\Factory\AnnouncementFactory;
use App\Factory\ExchangeFactory;
use App\Factory\UserFactory;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

/**
 * Itération 7 : confirmation de l'adresse email et mot de passe oublié.
 */
final class AccountEmailsTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
    }

    public function testRegistrationSendsConfirmationEmailAndLinkVerifiesAccount(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[username]' => 'nouveau',
            'registration_form[email]' => 'nouveau@bookswap.test',
            'registration_form[plainPassword]' => 'Un-Livre-Ouvert-2026!',
            'registration_form[agreeTerms]' => true,
        ]));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertEmailAddressContains($email, 'to', 'nouveau@bookswap.test');
        self::assertEmailHtmlBodyContains($email, 'Confirmer mon adresse');

        $user = UserFactory::repository()->findOneBy(['email' => 'nouveau@bookswap.test']);
        self::assertFalse($user->isVerified());

        // Connecté mais pas encore confirmé : bandeau et publication bloquée
        $this->client->followRedirect();
        self::assertSelectorExists('.verify-banner');
        $this->client->request('GET', '/books/new');
        self::assertResponseRedirects('/announcements');

        // Clic sur le lien de l'email
        $this->client->request('GET', $this->linkIn($email, 'Confirmer mon adresse'));
        self::assertResponseRedirects('/');
        refresh($user);
        self::assertTrue($user->isVerified());

        $this->client->request('GET', '/books/new');
        self::assertResponseIsSuccessful();
    }

    public function testTamperedConfirmationLinkIsRejected(): void
    {
        $user = UserFactory::createOne(['isVerified' => false]);

        $this->client->request('GET', '/verify/email?id='.$user->getId().'&expires=9999999999&signature=fausse&token=faux');

        self::assertResponseRedirects('/');
        refresh($user);
        self::assertFalse($user->isVerified());
    }

    public function testUnverifiedUserCannotRequestAnExchange(): void
    {
        $user = UserFactory::createOne(['isVerified' => false]);
        $announcement = AnnouncementFactory::createOne();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/announcements/'.$announcement->getId());
        $this->client->submit($crawler->selectButton('Demander ce livre')->form());

        ExchangeFactory::assert()->empty();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Confirme d\'abord ton adresse email');
    }

    public function testResendConfirmationEmail(): void
    {
        $this->client->loginUser(UserFactory::createOne(['isVerified' => false]));

        $crawler = $this->client->request('GET', '/');
        $this->client->submit($crawler->selectButton('Renvoyer le lien')->form());

        self::assertEmailCount(1);
    }

    public function testForgotPasswordFullFlow(): void
    {
        $user = UserFactory::createOne(['email' => 'alice@bookswap.test']);

        // 1. Demande
        $crawler = $this->client->request('GET', '/reset-password');
        $this->client->submit($crawler->selectButton('Envoyer le lien')->form(['reset_password_request_form[email]' => 'alice@bookswap.test']));
        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(1);

        $resetUrl = $this->linkIn(self::getMailerMessage(), 'Choisir un nouveau mot de passe');
        self::assertStringContainsString('/reset-password/reset/', $resetUrl);

        // 2. Clic sur le lien : le jeton est retiré de l'URL
        $this->client->request('GET', $resetUrl);
        self::assertResponseRedirects('/reset-password/reset');

        // 3. Nouveau mot de passe
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'reset_password_form[newPassword][first]' => 'Un-Livre-Ouvert-2026!',
            'reset_password_form[newPassword][second]' => 'Un-Livre-Ouvert-2026!',
        ]));
        self::assertResponseRedirects('/login');

        refresh($user);
        self::assertTrue(self::getContainer()->get('security.user_password_hasher')->isPasswordValid($user, 'Un-Livre-Ouvert-2026!'));

        // Le lien ne sert qu'une fois
        $this->client->request('GET', $resetUrl);
        $this->client->followRedirect();
        self::assertResponseRedirects('/reset-password');
    }

    public function testForgotPasswordDoesNotRevealUnknownEmails(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $this->client->submit($crawler->selectButton('Envoyer le lien')->form(['reset_password_request_form[email]' => 'inconnu@bookswap.test']));

        // Même réponse qu'avec une adresse connue, mais aucun email envoyé
        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(0);
    }

    public function testDeletedAccountCannotResetPassword(): void
    {
        $user = UserFactory::createOne(['email' => 'parti@bookswap.test']);
        $user->setDeletedAt(new \DateTimeImmutable());
        save($user);

        $crawler = $this->client->request('GET', '/reset-password');
        $this->client->submit($crawler->selectButton('Envoyer le lien')->form(['reset_password_request_form[email]' => 'parti@bookswap.test']));

        self::assertEmailCount(0);
    }

    public function testLoginPageLinksToForgotPassword(): void
    {
        $this->client->request('GET', '/login');

        self::assertSelectorExists('a[href="/reset-password"]');
    }

    /**
     * URL du bouton de l'email (le HTML est déjà rendu : on lit le lien dans le corps du message).
     */
    private function linkIn(?RawMessage $email, string $buttonText): string
    {
        self::assertInstanceOf(Email::class, $email);
        $crawler = new Crawler((string) $email->getHtmlBody());

        return (string) $crawler->selectLink($buttonText)->attr('href');
    }
}
