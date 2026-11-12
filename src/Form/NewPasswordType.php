<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Nouveau mot de passe saisi deux fois, avec les mêmes règles qu'à l'inscription.
 * Utilisé seul (mot de passe oublié) ou dans ChangePasswordType (page Mon profil).
 */
class NewPasswordType extends AbstractType
{
    public function getParent(): string
    {
        return RepeatedType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => ['label' => 'Nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'Confirme le nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'invalid_message' => 'Les deux mots de passe ne sont pas identiques.',
            'constraints' => [
                new NotBlank(message: 'Entre un nouveau mot de passe.'),
                new Length(max: 4096),
                new PasswordStrength(message: 'Ton mot de passe est trop faible : allonge-le ou mélange lettres, chiffres et symboles.'),
            ],
        ]);
    }
}
