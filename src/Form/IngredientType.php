<?php

namespace App\Form;

use App\Entity\Ingredient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class IngredientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The three fields sit side by side in one flex row (see the
        // .ingredient-item markup in recipe/_form.html.twig), so they carry
        // width classes only. Everything else comes from the form theme.
        $builder
            ->add('amount', NumberType::class, [
                'label' => false,
                'required' => false,
                'scale' => 2,
                'attr' => ['placeholder' => 'Menge', 'class' => 'w-20'],
            ])
            ->add('unit', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Einheit', 'class' => 'w-24'],
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Zutat', 'class' => 'min-w-[8rem] flex-1'],
                'constraints' => [new NotBlank()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ingredient::class,
        ]);
    }
}
