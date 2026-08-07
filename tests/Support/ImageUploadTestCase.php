<?php

namespace App\Tests\Support;

use App\Entity\Recipe;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Base for the tests that put files into the upload directory. It hands out
 * real image files, empties the directory around every test and can read back
 * what stayed there, so a test can state exactly which files a recipe left
 * behind.
 */
abstract class ImageUploadTestCase extends DatabaseTestCase
{
    protected string $uploadDir;
    protected string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadDir = static::getContainer()->getParameter('images_directory');
        $this->fixtureDir = sys_get_temp_dir().'/rezeptoria-image-test';

        $filesystem = new Filesystem();
        $filesystem->remove([$this->uploadDir, $this->fixtureDir]);
        $filesystem->mkdir($this->fixtureDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->uploadDir, $this->fixtureDir]);

        parent::tearDown();
    }

    /**
     * A recipe of the logged-in user, so the edit form is open to it.
     */
    protected function ownRecipe(): Recipe
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);

        return $this->createRecipe($owner);
    }

    /**
     * Submits the edit form. The recipe's own fields are filled in with
     * something valid, so a test only has to name what it is about.
     *
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $files
     */
    protected function submitEdit(string $slug, array $fields = [], array $files = []): void
    {
        $this->client->request('POST', '/'.$slug.'/edit', [
            'recipe' => array_replace([
                'title' => 'Käsekuchen',
                'text' => 'Ein Kuchen.',
                'baseServings' => 4,
            ], $fields),
        ], [
            'recipe' => $files,
        ]);
    }

    protected function pngFile(string $name): UploadedFile
    {
        $path = $this->fixtureDir.'/'.$name;
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);

        return new UploadedFile($path, $name, 'image/png', test: true);
    }

    protected function svgFile(string $name, string $clientMimeType): UploadedFile
    {
        $path = $this->fixtureDir.'/'.$name;
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        return new UploadedFile($path, $name, $clientMimeType, test: true);
    }

    /**
     * @return list<string>
     */
    protected function storedFiles(): array
    {
        if (!is_dir($this->uploadDir)) {
            return [];
        }

        return array_values(array_diff(scandir($this->uploadDir) ?: [], ['.', '..']));
    }
}
