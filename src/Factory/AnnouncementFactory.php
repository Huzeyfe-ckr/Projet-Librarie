<?php

namespace App\Factory;

use App\Entity\Announcement;
use App\Enum\AnnouncementStatus;
use App\Enum\AnnouncementType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Announcement>
 */
final class AnnouncementFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Announcement::class;
    }

    protected function defaults(): array
    {
        $type = self::faker()->randomElement(AnnouncementType::cases());

        return [
            'user' => UserFactory::new(),
            'title' => ucfirst(self::faker()->words(3, true)),
            'description' => self::faker()->sentence(12),
            'type' => $type,
            'price' => AnnouncementType::Sale === $type ? (string) self::faker()->randomFloat(2, 2, 30) : null,
            'status' => AnnouncementStatus::Available,
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-2 months')),
        ];
    }

    public function sale(float $price = 10.0): self
    {
        return $this->with(['type' => AnnouncementType::Sale, 'price' => number_format($price, 2, '.', '')]);
    }

    public function donation(): self
    {
        return $this->with(['type' => AnnouncementType::Donation, 'price' => null]);
    }

    public function inactive(): self
    {
        return $this->with(['inactivatedAt' => new \DateTimeImmutable('-1 day')]);
    }
}
