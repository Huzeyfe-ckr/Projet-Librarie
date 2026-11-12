<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\DeleteAccountType;
use App\Form\ProfileType;
use App\Repository\AnnouncementRepository;
use App\Repository\BookRepository;
use App\Repository\UserRepository;
use App\Service\AccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    /**
     * Profil public d'un membre : ses annonces en ligne.
     */
    #[Route('/users/{username}', name: 'user_show', requirements: ['username' => '[a-zA-Z0-9._-]+'], methods: ['GET'])]
    public function show(string $username, UserRepository $users, AnnouncementRepository $announcements, BookRepository $books): Response
    {
        $member = $users->findActiveByUsername($username) ?? throw $this->createNotFoundException('Membre introuvable.');
        $items = $announcements->findActiveBySeller($member);

        return $this->render('user/show.html.twig', [
            'member' => $member,
            'announcements' => $items,
            'booksByAnnouncement' => $books->findActiveByAnnouncements($items),
            'completedCount' => $announcements->countCompletedBySeller($member),
        ]);
    }

    /**
     * Mon profil : trois formulaires indépendants sur la même page (infos, mot de passe, suppression).
     */
    #[Route('/profile', name: 'profile_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(#[CurrentUser] User $user, Request $request, AccountManager $account, EntityManagerInterface $em): Response
    {
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted()) {
            if ($profileForm->isValid()) {
                $account->updateProfile($user, $profileForm->get('avatar')->getData(), (bool) $profileForm->get('removeAvatar')->getData());
                $this->addFlash('success', 'Ton profil a été mis à jour.');

                return $this->redirectToRoute('profile_edit');
            }

            // Le formulaire a déjà modifié l'utilisateur connecté en mémoire : on annule pour que le menu
            // n'affiche pas un pseudo refusé (par exemple déjà pris).
            $em->refresh($user);
        }

        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $account->changePassword($user, $passwordForm->get('newPassword')->getData());
            $this->addFlash('success', 'Ton mot de passe a été changé.');

            return $this->redirectToRoute('profile_edit');
        }

        $status = $profileForm->isSubmitted() || $passwordForm->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('profile/edit.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'deleteForm' => $this->createForm(DeleteAccountType::class, null, ['action' => $this->generateUrl('profile_delete')]),
        ], new Response(status: $status));
    }

    #[Route('/profile/delete', name: 'profile_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(#[CurrentUser] User $user, Request $request, AccountManager $account, Security $security): Response
    {
        $form = $this->createForm(DeleteAccountType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Mot de passe incorrect : ton compte n\'a pas été supprimé.');

            return $this->redirectToRoute('profile_edit', ['_fragment' => 'delete']);
        }

        $account->delete($user);
        $security->logout(false);
        $this->addFlash('success', 'Ton compte a été supprimé. Merci d\'avoir fait circuler des livres !');

        return $this->redirectToRoute('app_home');
    }
}
