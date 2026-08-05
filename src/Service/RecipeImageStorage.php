<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Owns the teaser image files under public/uploads/images: storing an upload
 * under a collision-free name and removing one that no recipe points at
 * anymore, so replaced and deleted images do not pile up on disk.
 */
final class RecipeImageStorage
{
    public function __construct(
        #[Autowire('%images_directory%')]
        private readonly string $directory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @return string the stored file name, to be kept on the recipe
     *
     * @throws FileException when the file cannot be written
     */
    public function store(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = $this->slugger->slug($originalFilename).'-'.uniqid().'.'.$file->guessExtension();

        $file->move($this->directory, $filename);

        return $filename;
    }

    public function remove(?string $filename): void
    {
        if (null === $filename || '' === $filename) {
            return;
        }

        // basename() keeps a manipulated database value from reaching outside
        // the upload directory.
        $path = $this->directory.'/'.basename($filename);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
