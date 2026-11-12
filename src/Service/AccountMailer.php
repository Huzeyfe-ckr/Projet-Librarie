<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Emails liés au compte : confirmation de l'adresse et réinitialisation du mot de passe.
 * En dev, ils arrivent dans Mailpit : http://localhost:8025.
 */
final class AccountMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        #[Autowire('%app.mailer_from%')]
        private readonly string $from,
    ) {
    }

    public function sendEmailConfirmation(User $user): void
    {
        // Lien signé (et daté) : impossible à fabriquer sans la clé secrète de l'application
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()],
        );

        $this->send($user, 'Confirme ton adresse email', 'emails/confirm_email.html.twig', [
            'confirmUrl' => $signature->getSignedUrl(),
            'expiresIn' => $signature->getExpiresAtIntervalInstance(),
        ]);
    }

    public function sendResetPassword(User $user, ResetPasswordToken $token): void
    {
        $this->send($user, 'Réinitialise ton mot de passe', 'emails/reset_password.html.twig', [
            'resetToken' => $token,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(User $user, string $subject, string $template, array $context): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->from, 'BookShare'))
            ->to(new Address((string) $user->getEmail(), (string) $user->getUsername()))
            ->subject($subject.' · BookShare')
            ->htmlTemplate($template)
            ->context(['user' => $user] + $context);

        $this->mailer->send($email);
    }
}
