<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    // Same rule as RegistrationFormType — a reset must not demand
                    // more than the account was allowed to be created with.
                    'constraints' => [
                        new NotBlank(
                            message: 'Bitte gib ein Passwort ein.',
                        ),
                        new Length(
                            min: 6,
                            minMessage: 'Dein Passwort muss mindestens {{ limit }} Zeichen lang sein.',
                            // max length allowed by Symfony for security reasons
                            max: 4096,
                        ),
                    ],
                    'label' => 'Neues Passwort',
                ],
                'second_options' => [
                    'label' => 'Passwort wiederholen',
                ],
                'invalid_message' => 'Die beiden Passwörter stimmen nicht überein.',
                // Instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
