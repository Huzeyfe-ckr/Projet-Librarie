<?php

namespace App\Factory;

use App\Entity\Wishlist;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Wishlist>
 */
final class WishlistFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Wishlist::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'book' => BookFactory::new(),
        ];
    }
}
