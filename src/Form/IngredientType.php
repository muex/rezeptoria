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
        $builder
            ->add('amount', NumberType::class, [
                'label' => false,
                'required' => false,
                'scale' => 2,
                'attr' => ['placeholder' => 'Menge', 'class' => 'w-24 border border-gray-300 rounded px-2 py-1 text-sm'],
            ])
            ->add('unit', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Einheit', 'class' => 'w-24 border border-gray-300 rounded px-2 py-1 text-sm'],
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Zutat', 'class' => 'flex-1 border border-gray-300 rounded px-2 py-1 text-sm'],
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
