<?php

namespace App\Factory;

use App\Entity\Comment;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Comment>
 */
final class CommentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Comment::class;
    }

    protected function defaults(): array
    {
        return [
            'announcement' => AnnouncementFactory::new(),
            'user' => UserFactory::new(),
            'content' => self::faker()->sentence(),
        ];
    }
}
