<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Enum\BookCondition;
use App\Repository\AnnouncementRepository;
use App\Repository\BookRepository;
use App\Repository\CommentRepository;
use App\Repository\ExchangeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/announcements')]
class AnnouncementController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('', name: 'announcement_index', methods: ['GET'])]
    public function index(
        Request $request,
        AnnouncementRepository $announcements,
        BookRepository $books,
    ): Response {
        $term = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));

        $result = $announcements->searchActive($term, $page, self::PER_PAGE);
        $items = iterator_to_array($result);

        return $this->render('announcement/index.html.twig', [
            'announcements' => $items,
            'booksByAnnouncement' => $books->findActiveByAnnouncements($items),
            'term' => $term,
            'page' => $page,
            'pages' => max(1, (int) ceil(count($result) / self::PER_PAGE)),
            'total' => count($result),
        ]);
    }

    #[Route('/{id}', name: 'announcement_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        AnnouncementRepository $announcements,
        BookRepository $books,
        CommentRepository $comments,
        ExchangeRepository $exchanges,
    ): Response {
        // Un admin peut ouvrir une annonce désactivée (pour la modérer), pas les autres.
        $announcement = $this->isGranted('ROLE_ADMIN')
            ? $announcements->find($id)
            : $announcements->findActiveById($id);

        if (null === $announcement) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $items = $books->findActiveByAnnouncements([$announcement])[$announcement->getId()] ?? [];

        $user = $this->getUser();
        $myExchange = $user instanceof User
            ? $exchanges->findOneBy(['announcement' => $announcement, 'requester' => $user])
            : null;

        return $this->render('announcement/show.html.twig', [
            'announcement' => $announcement,
            'books' => $items,
            'lot' => count($items) > 1 ? $this->buildLotSummary($items) : null,
            'comments' => $comments->findActiveByAnnouncement($announcement),
            'myExchange' => $myExchange,
        ]);
    }

    /**
     * @param Book[] $books
     *
     * @return array{count: int, authors: string[], genres: string[], conditions: array<int, array{condition: BookCondition, count: int}>}
     */
    private function buildLotSummary(array $books): array
    {
        $authors = [];
        $genres = [];
        $conditions = [];

        foreach ($books as $book) {
            if ($book->getAuthor()) {
                $authors[$book->getAuthor()] = $book->getAuthor();
            }
            if ($book->getGenre()) {
                $genres[$book->getGenre()] = $book->getGenre();
            }

            $condition = $book->getBookCondition();
            $key = $condition->value;
            $conditions[$key] ??= ['condition' => $condition, 'count' => 0];
            ++$conditions[$key]['count'];
        }

        return [
            'count' => count($books),
            'authors' => array_values($authors),
            'genres' => array_values($genres),
            'conditions' => array_values($conditions),
        ];
    }
}