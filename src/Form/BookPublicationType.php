<?php

namespace App\Form;

use App\Enum\AnnouncementType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class BookPublicationType extends AbstractType
{
    public const MAX_BOOKS = 100;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'annonce',
                'required' => false,
                'help' => 'Obligatoire pour un pack. Avec un seul livre, le titre du livre est repris si tu laisses vide.',
                'constraints' => [new Length(max: 255)],
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
                'help' => 'Pour un pack, c\'est le prix de l\'ensemble. Ignoré pour un don.',
                'constraints' => [new PositiveOrZero(message: 'Le prix ne peut pas être négatif.')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4],
                'constraints' => [new Length(max: 2000)],
            ])
            ->add('books', CollectionType::class, [
                'entry_type' => BookItemType::class,
                'entry_options' => ['label' => false],
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'constraints' => [
                    new Count(
                        min: 1,
                        max: self::MAX_BOOKS,
                        minMessage: 'Ajoute au moins un livre.',
                        maxMessage: 'Un pack contient au maximum {{ limit }} livres.',
                    ),
                ],
            ]);
    }
}