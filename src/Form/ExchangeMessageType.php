<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ExchangeMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', TextareaType::class, [
            'label' => false,
            'attr' => ['rows' => 3, 'placeholder' => 'Écris ton message…'],
            'constraints' => [
                new NotBlank(message: 'Écris un message.'),
                new Length(max: 2000, maxMessage: '2000 caractères maximum.'),
            ],
        ]);
    }
}
