<?php

namespace App\Service;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\User;
use App\Enum\AnnouncementStatus;
use App\Enum\AnnouncementType;
use App\Enum\ExchangeStatus;
use App\Exception\AnnouncementException;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Création, modification et retrait d'une annonce et de ses livres.
 *
 * Travaille avec les données du formulaire BookPublicationType :
 *   ['title' => ?string, 'type' => AnnouncementType, 'price' => ?float, 'description' => ?string,
 *    'books' => [['title', 'author', 'genre', 'isbn', 'bookCondition', 'photos' => UploadedFile[]], ...]]
 */
final class AnnouncementPublisher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookRepository $books,
        private readonly ExchangeManager $exchangeManager,
        private readonly BookPhotoUploader $photoUploader,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function publish(User $seller, array $data): Announcement
    {
        $announcement = (new Announcement())->setUser($seller);
        $this->em->persist($announcement);

        $this->applyData($announcement, $data);
        foreach ($data['books'] as $item) {
            $this->createBook($announcement, $item);
        }

        $this->em->flush();

        return $announcement;
    }

    /**
     * Met à jour l'annonce. Les livres sont associés par position :
     * les premiers sont modifiés, les nouveaux créés, ceux en trop désactivés (jamais supprimés).
     *
     * @param array<string, mixed> $data
     */
    public function update(Announcement $announcement, array $data): void
    {
        $this->applyData($announcement, $data);

        $existing = $this->activeBooks($announcement);
        foreach (array_values($data['books']) as $index => $item) {
            isset($existing[$index])
                ? $this->fillBook($existing[$index], $item)
                : $this->createBook($announcement, $item);
        }
        foreach (\array_slice($existing, \count($data['books'])) as $removed) {
            $removed->setInactivatedAt(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    /**
     * Données de l'annonce au format du formulaire (pour pré-remplir la page de modification).
     *
     * @return array<string, mixed>
     */
    public function toFormData(Announcement $announcement): array
    {
        $books = $this->activeBooks($announcement);

        return [
            // Pour un livre seul, le titre de l'annonce est celui du livre : on laisse le champ vide
            'title' => 1 === \count($books) && $books[0]->getTitle() === $announcement->getTitle() ? null : $announcement->getTitle(),
            'type' => $announcement->getType(),
            'price' => null !== $announcement->getPrice() ? (float) $announcement->getPrice() : null,
            'description' => $announcement->getDescription(),
            'books' => array_map(static fn (Book $book): array => [
                'title' => $book->getTitle(),
                'author' => $book->getAuthor(),
                'genre' => $book->getGenre(),
                'isbn' => $book->getIsbn(),
                'bookCondition' => $book->getBookCondition(),
            ], $books),
        ];
    }

    /**
     * Retire l'annonce du site. Les demandes en attente sont refusées automatiquement.
     */
    public function deactivate(Announcement $announcement, User $actor): void
    {
        if (AnnouncementStatus::Reserved === $announcement->getStatus()) {
            throw new AnnouncementException('Cette annonce est réservée : termine ou annule l\'échange en cours avant de la retirer.');
        }

        foreach ($announcement->getExchanges() as $exchange) {
            if (ExchangeStatus::Pending === $exchange->getStatus()) {
                $this->exchangeManager->refuse($exchange, $actor);
            }
        }

        $announcement->setInactivatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyData(Announcement $announcement, array $data): void
    {
        $title = trim((string) ($data['title'] ?? ''));
        $isDonation = AnnouncementType::Donation === $data['type'];

        $announcement
            // Avec un seul livre et sans titre, l'annonce prend le titre du livre
            ->setTitle('' !== $title ? $title : array_values($data['books'])[0]['title'])
            ->setDescription($data['description'] ?? null)
            ->setType($data['type'])
            ->setPrice($isDonation ? null : number_format((float) $data['price'], 2, '.', ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function createBook(Announcement $announcement, array $item): void
    {
        $book = (new Book())->setAnnouncement($announcement);
        $this->em->persist($book);
        $this->fillBook($book, $item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function fillBook(Book $book, array $item): void
    {
        $book
            ->setTitle($item['title'])
            ->setAuthor($item['author'] ?? null)
            ->setGenre($item['genre'] ?? null)
            ->setIsbn($item['isbn'] ?? null)
            ->setBookCondition($item['bookCondition']);

        // Les nouvelles photos s'ajoutent à celles déjà présentes
        foreach ($item['photos'] ?? [] as $photo) {
            $this->photoUploader->attach($book, $photo);
        }
    }

    /**
     * Livres en ligne de l'annonce, dans l'ordre de création (même ordre que le formulaire).
     *
     * @return Book[]
     */
    public function activeBooks(Announcement $announcement): array
    {
        $books = $this->books->findActiveByAnnouncements([$announcement])[$announcement->getId()] ?? [];
        usort($books, static fn (Book $a, Book $b): int => $a->getId() <=> $b->getId());

        return $books;
    }
}
