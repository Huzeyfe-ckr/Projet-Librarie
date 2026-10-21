<?php

namespace App\Form;

use App\Enum\AnnouncementType;
use App\Enum\BookCondition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class BookPublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du livre',
                'constraints' => [
                    new NotBlank(message: 'Indique le titre du livre.'),
                    new Length(max: 255),
                ],
            ])
            ->add('author', TextType::class, [
                'label' => 'Auteur',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('isbn', TextType::class, [
                'label' => 'ISBN',
                'required' => false,
                'help' => 'Optionnel. 10 ou 13 chiffres, avec ou sans tirets.',
                'constraints' => [new Isbn()],
            ])
            ->add('genre', TextType::class, [
                'label' => 'Genre',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('bookCondition', EnumType::class, [
                'class' => BookCondition::class,
                'label' => 'État du livre',
                'placeholder' => 'Choisir un état',
                'constraints' => [new NotBlank(message: 'Indique l\'état du livre.')],
            ])
            ->add('type', EnumType::class, [
                'class' => AnnouncementType::class,
                'label' => 'Type d\'annonce',
                'placeholder' => 'Choisir un type',
                'constraints' => [new NotBlank(message: 'Choisis un type d\'annonce.')],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'EUR',
                'required' => false,
                'help' => 'Laisse vide pour un don.',
                'constraints' => [new PositiveOrZero(message: 'Le prix ne peut pas être négatif.')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5],
                'constraints' => [new Length(max: 2000)],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Pas de data_class : le formulaire renvoie un tableau,
        // le contrôleur construit Announcement et Book.
        $resolver->setDefaults(['data_class' => null]);
    }
}