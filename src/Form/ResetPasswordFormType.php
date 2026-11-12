<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Choix du nouveau mot de passe après avoir cliqué sur le lien reçu par email.
 */
class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('newPassword', NewPasswordType::class);
    }
}
