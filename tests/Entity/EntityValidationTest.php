<?php

namespace App\Tests\Entity;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Enum\AnnouncementType;
use App\Enum\BookCondition;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Règles de validation posées sur les entités (#[Assert\…]).
 * Elles s'appliquent partout où l'entité est validée, notamment dans EasyAdmin.
 */
final class EntityValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testValidSaleAndDonationPass(): void
    {
        self::assertSame([], $this->errors($this->sale('12.50')));
        self::assertSame([], $this->errors($this->donation()));
    }

    /**
     * @param callable(Announcement): void $breakIt
     */
    #[DataProvider('invalidAnnouncements')]
    public function testInvalidAnnouncementIsRejected(callable $breakIt, string $field): void
    {
        $announcement = $this->sale('10.00');
        $breakIt($announcement);

        self::assertContains($field, $this->errors($announcement));
    }

    /**
     * @return iterable<string, array{callable(Announcement): void, string}>
     */
    public static function invalidAnnouncements(): iterable
    {
        yield 'titre vide' => [static fn (Announcement $a) => $a->setTitle(''), 'title'];
        yield 'titre en espaces' => [static fn (Announcement $a) => $a->setTitle('   '), 'title'];
        yield 'titre trop long' => [static fn (Announcement $a) => $a->setTitle(str_repeat('a', 256)), 'title'];
        yield 'description trop longue' => [static fn (Announcement $a) => $a->setDescription(str_repeat('a', 2001)), 'description'];
        yield 'prix négatif' => [static fn (Announcement $a) => $a->setPrice('-1.00'), 'price'];
        yield 'prix trop grand pour la colonne' => [static fn (Announcement $a) => $a->setPrice('100000000.00'), 'price'];
        yield 'vente sans prix' => [static fn (Announcement $a) => $a->setPrice(null), 'price'];
        yield 'don avec un prix' => [static fn (Announcement $a) => $a->setType(AnnouncementType::Donation), 'price'];
    }

    public function testValidBookPasses(): void
    {
        self::assertSame([], $this->errors($this->book()));
    }

    /**
     * @param callable(Book): void $breakIt
     */
    #[DataProvider('invalidBooks')]
    public function testInvalidBookIsRejected(callable $breakIt, string $field): void
    {
        $book = $this->book();
        $breakIt($book);

        self::assertContains($field, $this->errors($book));
    }

    /**
     * @return iterable<string, array{callable(Book): void, string}>
     */
    public static function invalidBooks(): iterable
    {
        yield 'titre vide' => [static fn (Book $b) => $b->setTitle('   '), 'title'];
        yield 'auteur trop long' => [static fn (Book $b) => $b->setAuthor(str_repeat('a', 256)), 'author'];
        yield 'genre trop long' => [static fn (Book $b) => $b->setGenre(str_repeat('a', 256)), 'genre'];
        yield 'ISBN invalide' => [static fn (Book $b) => $b->setIsbn('1234567890123'), 'isbn'];
    }

    private function sale(string $price): Announcement
    {
        return (new Announcement())
            ->setTitle('Dune')
            ->setType(AnnouncementType::Sale)
            ->setPrice($price);
    }

    private function donation(): Announcement
    {
        return (new Announcement())
            ->setTitle('Dune')
            ->setType(AnnouncementType::Donation)
            ->setPrice(null);
    }

    private function book(): Book
    {
        return (new Book())
            ->setTitle('Dune')
            ->setIsbn('9782266320481')
            ->setBookCondition(BookCondition::Good);
    }

    /**
     * @return string[] champs en erreur
     */
    private function errors(object $entity): array
    {
        $fields = [];
        foreach ($this->validator->validate($entity) as $violation) {
            $fields[] = $violation->getPropertyPath();
        }

        return $fields;
    }
}
