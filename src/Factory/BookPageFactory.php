<?php

namespace App\Factory;

use App\Entity\BookPage;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<BookPage>
 */
final class BookPageFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return BookPage::class;
    }

    protected function defaults(): array
    {
        return [
            'book' => BookFactory::new(),
            'imgUrl' => 'https://picsum.photos/seed/'.self::faker()->uuid().'/400/600',
            'pageOrder' => 1,
        ];
    }
}
