<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\User;
use App\Repository\AnnouncementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommentController extends AbstractController
{
    #[Route('/announcements/{id}/comments', name: 'comment_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function add(
        int $id,
        Request $request,
        AnnouncementRepository $announcements,
        EntityManagerInterface $em,
        #[CurrentUser] User $user,
    ): Response {
        $announcement = $announcements->findActiveById($id);
        if (null === $announcement) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $back = $this->redirectToRoute('announcement_show', ['id' => $id, '_fragment' => 'comments']);

        // Comme pour publier ou faire une demande : adresse email confirmée obligatoire
        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Confirme d\'abord ton adresse email pour publier un avis (lien dans le bandeau en haut de la page).');

            return $back;
        }

        if (!$this->isCsrfTokenValid('comment_'.$id, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, réessaie.');

            return $back;
        }

        $content = trim($request->request->getString('content'));
        $length = mb_strlen($content);

        if ($length < 3 || $length > 1000) {
            $this->addFlash('error', 'Ton avis doit faire entre 3 et 1000 caractères.');

            return $back;
        }

        $comment = (new Comment())
            ->setAnnouncement($announcement)
            ->setUser($user)
            ->setContent($content);

        $em->persist($comment);
        $em->flush();

        $this->addFlash('success', 'Ton avis a bien été publié.');

        return $back;
    }
}
