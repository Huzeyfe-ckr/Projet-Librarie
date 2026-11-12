<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AccountMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class VerifyEmailController extends AbstractController
{
    /**
     * Lien reçu par email. Fonctionne même sans être connecté (autre appareil, autre navigateur).
     */
    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verify(Request $request, UserRepository $users, VerifyEmailHelperInterface $helper, EntityManagerInterface $em): Response
    {
        $user = $users->find($request->query->getInt('id'));
        if (!$user instanceof User || null !== $user->getDeletedAt()) {
            $this->addFlash('error', 'Ce lien de confirmation n\'est pas valide.');

            return $this->redirectToRoute('app_home');
        }

        try {
            $helper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide ou a expiré. Connecte-toi pour en recevoir un nouveau.');

            return $this->redirectToRoute('app_home');
        }

        $user->setIsVerified(true);
        $em->flush();
        $this->addFlash('success', 'Ton adresse email est confirmée, tu peux publier et faire des demandes.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resend(#[CurrentUser] User $user, Request $request, AccountMailer $mailer): Response
    {
        if ($this->isCsrfTokenValid('verify_resend', $request->request->getString('_token')) && !$user->isVerified()) {
            $mailer->sendEmailConfirmation($user);
            $this->addFlash('success', \sprintf('Un nouveau lien de confirmation a été envoyé à %s.', $user->getEmail()));
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
    }
}
