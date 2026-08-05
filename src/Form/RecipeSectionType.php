<?php

namespace App\Form;

use App\Entity\RecipeSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['placeholder' => 'z.B. Kuchenboden, Teig, Sauce …'],
                // See RecipeType: an empty field must reach the validator as '',
                // not as null.
                'empty_data' => '',
            ])
            ->add('ingredients', CollectionType::class, [
                'entry_type' => IngredientType::class,
                'prototype_name' => '__ingredient__',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
            ->add('preparation', TextareaType::class, [
                'label' => 'Zubereitung',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Zubereitungsschritte …'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecipeSection::class,
        ]);
    }
}
