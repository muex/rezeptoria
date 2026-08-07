<?php

namespace App\Form;

use App\Entity\RecipeImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Both fields sit in one row (see the .gallery-item markup in
        // recipe/_form.html.twig), so they carry no labels of their own.
        $builder
            ->add('file', ImageUploadType::class, ['label' => false])
            ->add('caption', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Bildunterschrift (optional)'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecipeImage::class,
        ]);
    }
}
