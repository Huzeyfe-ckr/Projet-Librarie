<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Repository\BookRepository;
use App\Repository\WishlistRepository;
use App\Service\WishlistManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WishlistController extends AbstractController
{
    #[Route('/wishlist', name: 'wishlist_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, WishlistRepository $wishlists): Response
    {
        return $this->render('wishlist/index.html.twig', [
            'items' => $wishlists->findActiveForUser($user),
        ]);
    }

    /**
     * Ajoute ou retire un livre (bouton ♥). Revient sur la page d'où vient la demande.
     */
    #[Route('/books/{id}/wishlist', name: 'wishlist_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(int $id, Request $request, BookRepository $books, WishlistManager $manager, #[CurrentUser] User $user): Response
    {
        $book = $books->findOneBy(['id' => $id, 'inactivatedAt' => null]);
        if (!$book instanceof Book || null !== $book->getAnnouncement()->getInactivatedAt()) {
            throw $this->createNotFoundException('Livre introuvable.');
        }

        // Le bouton peut être cliqué depuis la page de l'annonce ou depuis la wishlist : on revient au même endroit
        $back = 'wishlist' === $request->request->getString('from')
            ? $this->redirectToRoute('wishlist_index')
            : $this->redirectToRoute('announcement_show', ['id' => $book->getAnnouncement()->getId(), '_fragment' => 'book-'.$book->getId()]);

        if (!$this->isCsrfTokenValid('wishlist_'.$book->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, réessaie.');

            return $back;
        }

        try {
            $added = $manager->toggle($user, $book);
            $this->addFlash('success', $added
                ? \sprintf('« %s » a été ajouté à ta wishlist.', $book->getTitle())
                : \sprintf('« %s » a été retiré de ta wishlist.', $book->getTitle()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $back;
    }
}
