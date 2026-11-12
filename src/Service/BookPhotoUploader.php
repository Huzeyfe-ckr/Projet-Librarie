<?php

namespace App\Service;

use App\Entity\Book;
use App\Entity\BookPage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ajoute une photo envoyée à un livre (crée la BookPage correspondante).
 */
final class BookPhotoUploader
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageStorage $storage,
    ) {
    }

    public function attach(Book $book, UploadedFile $file): BookPage
    {
        $page = (new BookPage())
            ->setImgUrl($this->storage->store($file, 'books'))
            ->setPageOrder(\count($book->getActivePages()) + 1);

        $book->addPage($page);
        $this->em->persist($page);

        return $page;
    }
}
