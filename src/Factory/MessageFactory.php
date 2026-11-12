<?php

namespace App\Factory;

use App\Entity\Message;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Message>
 */
final class MessageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Message::class;
    }

    protected function defaults(): array
    {
        return [
            'exchange' => ExchangeFactory::new(),
            'sender' => UserFactory::new(),
            'content' => self::faker()->sentence(),
        ];
    }
}
