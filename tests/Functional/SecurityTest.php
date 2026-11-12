<?php

namespace App\Tests\Functional;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Connexion, déconnexion et comptes désactivés.
 */
final class SecurityTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testUserCanLogIn(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'alice@bookswap.test']);

        $this->login($client, 'alice@bookswap.test', 'password');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorExists('form.nav__logout');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'alice@bookswap.test']);

        $this->login($client, 'alice@bookswap.test', 'mauvais');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('.flash--error, .alert, [role="alert"]');
    }

    public function testInactiveAccountCannotLogIn(): void
    {
        $client = self::createClient();
        UserFactory::new()->inactive()->create(['email' => 'oceane@bookswap.test']);

        $this->login($client, 'oceane@bookswap.test', 'password');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertStringContainsString('Ce compte est désactivé.', (string) $client->getResponse()->getContent());
    }

    public function testLogoutWithoutCsrfTokenIsRefused(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/logout');

        // Un simple lien (ou une image piégée sur un autre site) ne doit pas pouvoir déconnecter l'utilisateur
        self::assertResponseStatusCodeSame(403);
    }

    public function testLogoutFormLogsTheUserOut(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());
        $client->setServerParameter('HTTP_ORIGIN', 'http://localhost');

        $client->request('GET', '/');
        $client->submit($client->getCrawler()->filter('form.nav__logout')->form());

        self::assertResponseRedirects('/login');
        $client->request('GET', '/exchanges');
        self::assertResponseRedirects('/login');
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form(['email' => $email, 'password' => $password]);
        $client->submit($form);
    }
}
