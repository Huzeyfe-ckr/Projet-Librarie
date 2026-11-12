<?php

namespace App\Controller;

use App\Entity\BookPage;
use App\Entity\User;
use App\Form\BookPublicationType;
use App\Security\Voter\AnnouncementVoter;
use App\Service\AnnouncementPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/books')]
class BookController extends AbstractController
{
    #[Route('/new', name: 'book_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, AnnouncementPublisher $publisher, #[CurrentUser] User $user): Response
    {
        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Confirme d\'abord ton adresse email pour publier un livre (lien dans le bandeau en haut de la page).');

            return $this->redirectToRoute('announcement_index');
        }

        // Une ligne de livre vide au départ
        $form = $this->createForm(BookPublicationType::class, ['books' => [[]]]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $announcement = $publisher->publish($user, $form->getData());
            $count = \count($form->getData()['books']);

            $this->addFlash('success', $count > 1
                ? \sprintf('Ton pack de %d livres a bien été publié.', $count)
                : 'Ton livre a bien été publié.');

            return $this->redirectToRoute('announcement_show', ['id' => $announcement->getId()]);
        }

        return $this->render('book/new.html.twig', [
            'form' => $form,
            'maxBooks' => BookPublicationType::MAX_BOOKS,
        ]);
    }

    /**
     * Supprime une photo depuis la page de modification de l'annonce (soft delete).
     */
    #[Route('/photos/{id}/delete', name: 'book_page_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePhoto(BookPage $page, Request $request, EntityManagerInterface $em): Response
    {
        $announcement = $page->getBook()->getAnnouncement();
        $this->denyAccessUnlessGranted(AnnouncementVoter::EDIT, $announcement, 'Tu ne peux pas modifier cette annonce.');

        if ($this->isCsrfTokenValid('book_page_delete_'.$page->getId(), $request->request->getString('_token'))) {
            $page->setInactivatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Photo supprimée.');
        } else {
            $this->addFlash('error', 'Session expirée, réessaie.');
        }

        return $this->redirectToRoute('announcement_edit', ['id' => $announcement->getId()]);
    }
}
