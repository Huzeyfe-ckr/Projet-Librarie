<?php

namespace App\Factory;

use App\Entity\Exchange;
use App\Enum\ExchangeStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Exchange>
 */
final class ExchangeFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Exchange::class;
    }

    protected function defaults(): array
    {
        return [
            'announcement' => AnnouncementFactory::new(),
            'requester' => UserFactory::new(),
            'status' => ExchangeStatus::Pending,
        ];
    }
}
