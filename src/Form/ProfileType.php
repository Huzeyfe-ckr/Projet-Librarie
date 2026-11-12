<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Informations publiques du profil : pseudo et avatar.
 * Les règles du pseudo (longueur, caractères, unicité) sont sur l'entité User.
 */
class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                'help' => 'Visible par les autres membres et dans l\'adresse de ton profil.',
            ])
            ->add('avatar', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'help' => 'JPEG, PNG ou WebP, 2 Mo maximum.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Envoie une image JPEG, PNG ou WebP.',
                        maxSizeMessage: 'Image trop lourde ({{ size }} {{ suffix }}), 2 Mo maximum.',
                    ),
                ],
            ])
            ->add('removeAvatar', CheckboxType::class, [
                'label' => 'Supprimer ma photo de profil',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
