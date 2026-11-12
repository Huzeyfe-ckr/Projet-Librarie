<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Règles du pseudo : voir l'entité User (communes avec la page de profil)
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                'help' => 'Visible par les autres membres. Lettres, chiffres, « . », « - » ou « _ ».',
            ])
            ->add('email', EmailType::class)
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Tu dois accepter les conditions.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Entre un mot de passe.'),
                    // Longueur max imposée par Symfony pour éviter les attaques par mot de passe géant
                    new Length(max: 4096),
                    // Vérifie la robustesse réelle (longueur + variété des caractères), ce qui rend un "min: 6" inutile
                    new PasswordStrength(
                        message: 'Ton mot de passe est trop faible : allonge-le ou mélange lettres, chiffres et symboles.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
