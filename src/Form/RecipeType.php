<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Recipe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('text', TextareaType::class, [
                'label' => 'Einleitung / Beschreibung',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('baseServings', IntegerType::class, [
                'label' => 'Grundmenge (Portionen)',
                'attr' => ['min' => 1],
            ])
            ->add('sections', CollectionType::class, [
                'entry_type' => RecipeSectionType::class,
                'prototype_name' => '__section__',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
            ->add('teaserImage', FileType::class, [
                'label' => 'Titelbild',
                'required' => false,
                'mapped' => false,
            ])
            ->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recipe::class,
        ]);
    }
}
