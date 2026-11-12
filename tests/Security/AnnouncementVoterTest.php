<?php

namespace App\Tests\Security;

use App\Entity\Announcement;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Security\Voter\AnnouncementVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AnnouncementVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, AnnouncementStatus, bool, bool}>
     */
    public static function cases(): iterable
    {
        // [qui, attribut, statut, annonce retirée ?, autorisé ?]
        yield 'le propriétaire modifie' => ['owner', AnnouncementVoter::EDIT, AnnouncementStatus::Available, false, true];
        yield 'un autre ne modifie pas' => ['other', AnnouncementVoter::EDIT, AnnouncementStatus::Available, false, false];
        yield 'l\'admin ne modifie pas le contenu' => ['admin', AnnouncementVoter::EDIT, AnnouncementStatus::Available, false, false];
        yield 'pas de modification si réservée' => ['owner', AnnouncementVoter::EDIT, AnnouncementStatus::Reserved, false, false];
        yield 'pas de modification si retirée' => ['owner', AnnouncementVoter::EDIT, AnnouncementStatus::Available, true, false];

        yield 'le propriétaire retire' => ['owner', AnnouncementVoter::DEACTIVATE, AnnouncementStatus::Available, false, true];
        yield 'l\'admin retire' => ['admin', AnnouncementVoter::DEACTIVATE, AnnouncementStatus::Available, false, true];
        yield 'un autre ne retire pas' => ['other', AnnouncementVoter::DEACTIVATE, AnnouncementStatus::Available, false, false];
        yield 'pas de retrait si réservée' => ['owner', AnnouncementVoter::DEACTIVATE, AnnouncementStatus::Reserved, false, false];
        yield 'retrait d\'une annonce terminée' => ['owner', AnnouncementVoter::DEACTIVATE, AnnouncementStatus::Completed, false, true];
        yield 'pas de double retrait' => ['owner', AnnouncementVoter::DEACTIVATE, AnnouncementStatus::Available, true, false];
    }

    #[DataProvider('cases')]
    public function testVote(string $who, string $attribute, AnnouncementStatus $status, bool $inactive, bool $expected): void
    {
        $users = [
            'owner' => $this->user(1),
            'other' => $this->user(2),
            'admin' => $this->user(3, ['ROLE_ADMIN']),
        ];

        $announcement = (new Announcement())
            ->setUser($users['owner'])
            ->setStatus($status)
            ->setInactivatedAt($inactive ? new \DateTimeImmutable() : null);

        $token = new UsernamePasswordToken($users[$who], 'main', $users[$who]->getRoles());
        $result = (new AnnouncementVoter())->vote($token, $announcement, [$attribute]);

        self::assertSame($expected ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * @param list<string> $roles
     */
    private function user(int $id, array $roles = []): User
    {
        $user = (new User())->setRoles($roles);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
