<?php

namespace App\Service;

use App\Entity\Recipe;
use App\Entity\RecipeImage;
use App\Entity\RecipeSection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Connects a submitted recipe form to the files on disk: it moves the uploads
 * into the upload directory, points the recipe at them, and names the files
 * that no longer belong to it so they can be deleted.
 *
 * A recipe carries images in three places — the teaser, the gallery and one
 * per section — and all three have to survive the same accidents: an upload
 * that cannot be written must never leave the recipe pointing at a file that
 * is not there, and a picture that was replaced or removed must not stay on
 * disk forever.
 */
final class RecipeImageUpdater
{
    public function __construct(
        private readonly RecipeImageStorage $storage,
    ) {
    }

    /**
     * Every file the recipe points at right now.
     *
     * @return list<string>
     */
    public function referencedFiles(Recipe $recipe): array
    {
        $files = [$recipe->getTeaserImage()];

        foreach ($recipe->getImages() as $image) {
            $files[] = $image->getFilename();
        }

        foreach ($recipe->getSections() as $section) {
            $files[] = $section->getImage();
        }

        return array_values(array_filter($files, static fn (?string $file): bool => null !== $file && '' !== $file));
    }

    /**
     * Stores what was uploaded and hands the recipe the file names. An upload
     * that fails leaves the previous image in place rather than replacing it
     * with a broken reference.
     *
     * @return list<string> one message per upload that could not be stored
     */
    public function apply(FormInterface $form, Recipe $recipe): array
    {
        $failures = [];

        $teaser = $this->store($form->get('teaserImage'), $failures, 'Das Titelbild konnte nicht gespeichert werden.');
        if (null !== $teaser) {
            $recipe->setTeaserImage($teaser);
        } elseif ($this->wasRemoved($form->get('removeTeaserImage'))) {
            $recipe->setTeaserImage(null);
        }

        foreach ($form->get('sections') as $sectionForm) {
            $section = $sectionForm->getData();
            if (!$section instanceof RecipeSection) {
                continue;
            }

            $file = $this->store(
                $sectionForm->get('image'),
                $failures,
                sprintf('Das Bild für den Abschnitt „%s“ konnte nicht gespeichert werden.', (string) $section->getTitle()),
            );

            if (null !== $file) {
                $section->setImage($file);
            } elseif ($this->wasRemoved($sectionForm->get('removeImage'))) {
                $section->setImage(null);
            }
        }

        $position = 0;
        foreach ($form->get('images') as $imageForm) {
            $image = $imageForm->getData();
            if (!$image instanceof RecipeImage) {
                continue;
            }

            $file = $this->store($imageForm->get('file'), $failures, 'Ein Galeriebild konnte nicht gespeichert werden.');
            if (null !== $file) {
                $image->setFilename($file);
            }

            if (null === $image->getFilename()) {
                // A row that was added but never given a file. It has nothing
                // to show, so it is dropped instead of reaching the database,
                // where its file name may not be null.
                $recipe->removeImage($image);

                continue;
            }

            $image->setPosition($position++);
        }

        return $failures;
    }

    /**
     * Deletes the files the recipe pointed at before the form was applied and
     * does not point at anymore — replaced teasers, removed gallery images.
     * Call it once the recipe itself is saved, so a failed save cannot take
     * the files of a recipe that still references them.
     *
     * @param list<string> $before the result of referencedFiles() from before the form was applied
     */
    public function removeReplaced(array $before, Recipe $recipe): void
    {
        $this->removeAll(array_diff($before, $this->referencedFiles($recipe)));
    }

    /**
     * @param iterable<string> $files
     */
    public function removeAll(iterable $files): void
    {
        foreach ($files as $file) {
            $this->storage->remove($file);
        }
    }

    /**
     * Whether the "remove this image" box was ticked. It is only asked after
     * an upload was ruled out: picking a new file says more clearly what the
     * image should be than a checkbox left over from before.
     */
    private function wasRemoved(FormInterface $field): bool
    {
        return true === $field->getData();
    }

    /**
     * @param list<string> $failures collects $failureMessage when the upload cannot be written
     *
     * @return string|null the stored file name, or null when nothing was uploaded or the upload failed
     */
    private function store(FormInterface $field, array &$failures, string $failureMessage): ?string
    {
        $file = $field->getData();
        if (!$file instanceof UploadedFile) {
            return null;
        }

        try {
            return $this->storage->store($file);
        } catch (FileException) {
            $failures[] = $failureMessage;

            return null;
        }
    }
}
