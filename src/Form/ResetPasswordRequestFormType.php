<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse email de ton compte',
            'attr' => ['autocomplete' => 'email', 'placeholder' => 'toi@exemple.fr'],
            'constraints' => [
                new NotBlank(message: 'Indique ton adresse email.'),
                new Email(message: 'Cette adresse email n\'est pas valide.'),
            ],
        ]);
    }
}
