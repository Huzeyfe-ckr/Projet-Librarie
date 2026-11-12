<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Service\AccountMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Mot de passe oublié, en 3 étapes : 1) saisir son email, 2) cliquer sur le lien reçu, 3) choisir un nouveau mot de passe.
 */
#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users, AccountMailer $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $users->findOneBy(['email' => $form->get('email')->getData(), 'deletedAt' => null, 'inactivatedAt' => null]);

            // On répond toujours la même chose, que l'adresse existe ou non :
            // impossible de savoir par ce formulaire qui est inscrit sur le site.
            $resetToken = null;
            if ($user instanceof User) {
                try {
                    $resetToken = $this->resetPasswordHelper->generateResetToken($user);
                    $mailer->sendResetPassword($user, $resetToken);
                } catch (ResetPasswordExceptionInterface) {
                    // Demande trop récente (anti-spam) : on ne renvoie pas d'email, sans le dire
                }
            }

            $this->setTokenObjectInSession($resetToken ?? $this->resetPasswordHelper->generateFakeResetToken());

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', ['requestForm' => $form]);
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        if (null === $this->getTokenObjectFromSession()) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        return $this->render('reset_password/check_email.html.twig');
    }

    #[Route('/reset/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        ?string $token = null,
    ): Response {
        if (null !== $token) {
            // Le jeton est rangé en session puis retiré de l'URL : il ne fuit pas dans l'historique ni dans le Referer
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Aucun lien de réinitialisation en cours.');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->addFlash('error', 'Ce lien n\'est plus valable (expiré ou déjà utilisé). Fais une nouvelle demande.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le lien ne sert qu'une fois
            $this->resetPasswordHelper->removeResetRequest($token);

            $user->setPassword($hasher->hashPassword($user, $form->get('newPassword')->getData()));
            $em->flush();
            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Ton mot de passe a été changé. Tu peux te connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', ['resetForm' => $form]);
    }
}
