<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * Changement de mot de passe depuis « Mon profil » : l'ancien est demandé (UserPassword) pour qu'une session
 * laissée ouverte ne suffise pas à prendre le contrôle du compte.
 */
class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new UserPassword(message: 'Mot de passe actuel incorrect.')],
            ])
            ->add('newPassword', NewPasswordType::class);
    }
}
