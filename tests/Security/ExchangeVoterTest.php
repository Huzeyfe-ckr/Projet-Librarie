<?php

namespace App\Tests\Security;

use App\Entity\Announcement;
use App\Entity\Exchange;
use App\Entity\User;
use App\Enum\ExchangeStatus;
use App\Security\Voter\ExchangeVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Test unitaire pur (sans base de données) de la matrice des droits sur un échange.
 */
final class ExchangeVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ExchangeStatus, bool}>
     */
    public static function cases(): iterable
    {
        // [qui, attribut, statut de l'échange, autorisé ?]
        yield 'le vendeur voit' => ['seller', ExchangeVoter::VIEW, ExchangeStatus::Pending, true];
        yield 'le demandeur voit' => ['buyer', ExchangeVoter::VIEW, ExchangeStatus::Refused, true];
        yield 'un tiers ne voit pas' => ['outsider', ExchangeVoter::VIEW, ExchangeStatus::Pending, false];

        yield 'écrire si en attente' => ['buyer', ExchangeVoter::MESSAGE, ExchangeStatus::Pending, true];
        yield 'écrire si accepté' => ['seller', ExchangeVoter::MESSAGE, ExchangeStatus::Accepted, true];
        yield 'pas écrire si refusé' => ['buyer', ExchangeVoter::MESSAGE, ExchangeStatus::Refused, false];
        yield 'pas écrire si terminé' => ['seller', ExchangeVoter::MESSAGE, ExchangeStatus::Completed, false];

        yield 'le vendeur accepte' => ['seller', ExchangeVoter::ACCEPT, ExchangeStatus::Pending, true];
        yield 'le demandeur n\'accepte pas' => ['buyer', ExchangeVoter::ACCEPT, ExchangeStatus::Pending, false];
        yield 'pas accepter deux fois' => ['seller', ExchangeVoter::ACCEPT, ExchangeStatus::Accepted, false];
        yield 'le vendeur refuse' => ['seller', ExchangeVoter::REFUSE, ExchangeStatus::Pending, true];

        yield 'le vendeur termine un échange accepté' => ['seller', ExchangeVoter::COMPLETE, ExchangeStatus::Accepted, true];
        yield 'pas terminer une demande en attente' => ['seller', ExchangeVoter::COMPLETE, ExchangeStatus::Pending, false];
        yield 'le demandeur ne termine pas' => ['buyer', ExchangeVoter::COMPLETE, ExchangeStatus::Accepted, false];

        yield 'le demandeur annule sa demande' => ['buyer', ExchangeVoter::CANCEL, ExchangeStatus::Pending, true];
        yield 'le demandeur annule un échange accepté' => ['buyer', ExchangeVoter::CANCEL, ExchangeStatus::Accepted, true];
        yield 'le vendeur annule un échange accepté' => ['seller', ExchangeVoter::CANCEL, ExchangeStatus::Accepted, true];
        yield 'le vendeur n\'annule pas une demande en attente (il refuse)' => ['seller', ExchangeVoter::CANCEL, ExchangeStatus::Pending, false];
        yield 'pas annuler un échange terminé' => ['buyer', ExchangeVoter::CANCEL, ExchangeStatus::Completed, false];
    }

    #[DataProvider('cases')]
    public function testVote(string $who, string $attribute, ExchangeStatus $status, bool $expected): void
    {
        $users = ['seller' => $this->user(1), 'buyer' => $this->user(2), 'outsider' => $this->user(3)];

        $exchange = (new Exchange())
            ->setAnnouncement((new Announcement())->setUser($users['seller']))
            ->setRequester($users['buyer'])
            ->setStatus($status);

        $token = new UsernamePasswordToken($users[$who], 'main', ['ROLE_USER']);
        $result = (new ExchangeVoter())->vote($token, $exchange, [$attribute]);

        self::assertSame($expected ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $result);
    }

    private function user(int $id): User
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
