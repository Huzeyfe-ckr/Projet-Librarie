<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\Book;
use App\Entity\User;
use App\Enum\BookCondition;
use App\Exception\AnnouncementException;
use App\Form\BookPublicationType;
use App\Model\AnnouncementSearch;
use App\Repository\AnnouncementRepository;
use App\Repository\BookRepository;
use App\Repository\CommentRepository;
use App\Repository\ExchangeRepository;
use App\Repository\WishlistRepository;
use App\Security\Voter\AnnouncementVoter;
use App\Service\AnnouncementPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        $search = AnnouncementSearch::fromRequest($request);
        $page = max(1, $request->query->getInt('page', 1));

        $result = $announcements->searchActive($search, $page, self::PER_PAGE);
        $items = iterator_to_array($result);

        return $this->render('announcement/index.html.twig', [
            'announcements' => $items,
            'booksByAnnouncement' => $books->findActiveByAnnouncements($items),
            'search' => $search,
            'term' => $search->term,
            'genres' => $books->findGenres(),
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
        WishlistRepository $wishlists,
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
            // livres de l'annonce déjà dans la wishlist de l'utilisateur (cœur plein)
            'wishlisted' => $user instanceof User ? $wishlists->findBookIdsForUser($user, $items) : [],
        ]);
    }

    #[Route('/mine', name: 'announcement_mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(
        #[CurrentUser] User $user,
        AnnouncementRepository $announcements,
        BookRepository $books,
    ): Response {
        $items = $announcements->findByOwner($user);

        return $this->render('announcement/mine.html.twig', [
            'announcements' => $items,
            'booksByAnnouncement' => $books->findActiveByAnnouncements($items),
            'pendingRequests' => $announcements->countPendingRequestsByOwner($user),
        ]);
    }

    #[Route('/{id}/edit', name: 'announcement_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(AnnouncementVoter::EDIT, subject: 'announcement')]
    public function edit(Announcement $announcement, Request $request, AnnouncementPublisher $publisher): Response
    {
        $form = $this->createForm(BookPublicationType::class, $publisher->toFormData($announcement));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publisher->update($announcement, $form->getData());
            $this->addFlash('success', 'Ton annonce a bien été modifiée.');

            return $this->redirectToRoute('announcement_show', ['id' => $announcement->getId()]);
        }

        return $this->render('book/new.html.twig', [
            'form' => $form,
            'maxBooks' => BookPublicationType::MAX_BOOKS,
            'announcement' => $announcement,
            'existingBooks' => $publisher->activeBooks($announcement),
        ]);
    }

    #[Route('/{id}/deactivate', name: 'announcement_deactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deactivate(
        Announcement $announcement,
        Request $request,
        AnnouncementPublisher $publisher,
        #[CurrentUser] User $user,
    ): Response {
        $this->denyAccessUnlessGranted(AnnouncementVoter::DEACTIVATE, $announcement, 'Tu ne peux pas retirer cette annonce.');

        if (!$this->isCsrfTokenValid('announcement_deactivate_'.$announcement->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, réessaie.');

            return $this->redirectToRoute('announcement_show', ['id' => $announcement->getId()]);
        }

        try {
            $publisher->deactivate($announcement, $user);
            $this->addFlash('success', 'Ton annonce a été retirée du site. Les demandes en attente ont été refusées.');
        } catch (AnnouncementException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('announcement_show', ['id' => $announcement->getId()]);
        }

        return $this->redirectToRoute('announcement_mine');
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
