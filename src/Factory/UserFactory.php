<?php

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    /** Hash bcrypt de « password » : évite de hasher à chaque utilisateur créé (beaucoup plus rapide). */
    public const PASSWORD_HASH = '$2y$10$iGRChCDrkZqRNnwLmh4RX.QU1sLrWbeqhxYd0hi3rLRHRTa8H0Mvu';

    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        $username = self::faker()->unique()->userName();

        return [
            'username' => $username,
            'email' => $username.'@bookswap.test',
            'password' => self::PASSWORD_HASH,
            'roles' => [],
            'isVerified' => true,
            'cguValidatedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-3 months')),
        ];
    }

    public function admin(): self
    {
        return $this->with(['roles' => ['ROLE_ADMIN']]);
    }

    public function inactive(): self
    {
        return $this->with(['inactivatedAt' => new \DateTimeImmutable('-1 day')]);
    }
}
