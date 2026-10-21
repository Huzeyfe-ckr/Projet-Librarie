<?php

namespace App\Form;

use App\Enum\BookCondition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class BookItemType extends AbstractType
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
            ->add('genre', TextType::class, [
                'label' => 'Genre',
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('isbn', TextType::class, [
                'label' => 'ISBN',
                'required' => false,
                'constraints' => [new Isbn()],
            ])
            ->add('bookCondition', EnumType::class, [
                'class' => BookCondition::class,
                'label' => 'État',
                'placeholder' => 'Choisir un état',
                'constraints' => [new NotBlank(message: 'Indique l\'état du livre.')],
            ]);
    }
}