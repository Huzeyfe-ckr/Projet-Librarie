<?php

namespace App\Factory;

use App\Entity\Book;
use App\Enum\BookCondition;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Book>
 */
final class BookFactory extends PersistentObjectFactory
{
    private const GENRES = ['Roman', 'Classique', 'Fantasy', 'Science-fiction', 'Policier', 'Manga', 'Bande dessinée', 'Jeunesse', 'Essai'];

    public static function class(): string
    {
        return Book::class;
    }

    protected function defaults(): array
    {
        return [
            'announcement' => AnnouncementFactory::new(),
            'title' => ucfirst(self::faker()->words(self::faker()->numberBetween(1, 4), true)),
            'author' => self::faker()->name(),
            'isbn' => self::faker()->isbn13(),
            'bookCondition' => self::faker()->randomElement(BookCondition::cases()),
            'genre' => self::faker()->randomElement(self::GENRES),
        ];
    }
}
