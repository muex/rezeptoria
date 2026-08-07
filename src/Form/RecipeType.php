<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Recipe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // empty_data keeps an empty field from reaching setTitle(string) as
            // null, which would fail with a 500 before validation could report it.
            ->add('title', TextType::class, ['empty_data' => ''])
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
            ->add('teaserImage', ImageUploadType::class, ['label' => 'Titelbild'])
            // Only offered once there is an image to take away — see the
            // image_field macro in recipe/_form.html.twig.
            ->add('removeTeaserImage', CheckboxType::class, [
                'label' => 'Titelbild entfernen',
                'required' => false,
                'mapped' => false,
            ])
            ->add('images', CollectionType::class, [
                'entry_type' => RecipeImageType::class,
                'prototype_name' => '__image__',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
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
