<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * A file field for the images of a recipe — teaser, gallery and section picture
 * all go through here, so the rules they are judged by cannot drift apart.
 *
 * The field is always unmapped: what the entity keeps is the name of the file
 * that RecipeImageUpdater wrote to disk, not the upload itself.
 */
final class ImageUploadType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'mapped' => false,
            'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            // Uploads land in public/uploads/images and are served from our
            // own origin, so anything but a real raster image is a liability
            // — an SVG carrying a <script> would run as first-party code.
            'constraints' => [
                new File(
                    // Matches PHP's upload_max_filesize; raise both together.
                    maxSize: '2M',
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
        ]);
    }

    public function getParent(): string
    {
        return FileType::class;
    }
}
