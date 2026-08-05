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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

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
            ->add('teaserImage', FileType::class, [
                'label' => 'Titelbild',
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                // Uploads land in public/uploads/images and are served from our
                // own origin, so anything but a real raster image is a liability
                // — an SVG carrying a <script> would run as first-party code.
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        // Checks the extension *and* that the content matches it,
                        // so an SVG renamed to .png is rejected as well.
                        extensions: ['jpg', 'jpeg', 'png', 'webp'],
                        notFoundMessage: 'Das Bild konnte nicht gelesen werden.',
                        maxSizeMessage: 'Das Bild ist zu groß ({{ size }} {{ suffix }}). Erlaubt sind maximal {{ limit }} {{ suffix }}.',
                        mimeTypesMessage: 'Bitte lade ein Bild im Format JPG, PNG oder WebP hoch.',
                        extensionsMessage: 'Bitte lade ein Bild im Format JPG, PNG oder WebP hoch.',
                        // No size placeholder here: PHP reports this limit in raw bytes.
                        uploadIniSizeErrorMessage: 'Das Bild überschreitet das Upload-Limit des Servers.',
                        uploadFormSizeErrorMessage: 'Das Bild ist zu groß.',
                        uploadErrorMessage: 'Das Bild konnte nicht hochgeladen werden.',
                    ),
                ],
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
