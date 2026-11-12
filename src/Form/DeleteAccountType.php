<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * Suppression du compte : action irréversible, on redemande le mot de passe.
 */
class DeleteAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'label' => 'Ton mot de passe, pour confirmer',
            'attr' => ['autocomplete' => 'current-password'],
            'constraints' => [new UserPassword(message: 'Mot de passe incorrect.')],
        ]);
    }
}
