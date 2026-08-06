<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploads are served from our own origin, so what lands in the upload
 * directory has to be a real image — and nothing may stay there once no
 * recipe points at it.
 */
final class RecipeImageTest extends DatabaseTestCase
{
    private string $uploadDir;
    private string $fixtureDir;

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

    public function testAnSvgIsRejected(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), $this->svgFile('schaedlich.svg', 'image/svg+xml'));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.text-red-600', 'Bitte lade ein Bild im Format JPG, PNG oder WebP hoch.');
        self::assertSame([], $this->storedFiles());
    }

    /**
     * Renaming the file is not enough: the constraint checks the content.
     */
    public function testAnSvgRenamedToPngIsRejected(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), $this->svgFile('harmlos.png', 'image/png'));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([], $this->storedFiles());
    }

    public function testAValidImageIsStored(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), $this->pngFile('kuchen.png'));

        self::assertResponseRedirects();
        self::assertCount(1, $this->storedFiles());

        $stored = $this->reloadRecipe($recipe->getSlug())->getTeaserImage();
        self::assertNotNull($stored);
        self::assertContains($stored, $this->storedFiles());
    }

    public function testReplacingTheImageRemovesTheOldFile(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), $this->pngFile('erstes.png'));
        $first = $this->reloadRecipe($recipe->getSlug())->getTeaserImage();

        $this->submitEdit($recipe->getSlug(), $this->pngFile('zweites.png'));
        $second = $this->reloadRecipe($recipe->getSlug())->getTeaserImage();

        self::assertNotSame($first, $second);
        self::assertSame([$second], $this->storedFiles(), 'the replaced file should be gone');
    }

    public function testDeletingTheRecipeRemovesItsImage(): void
    {
        $recipe = $this->ownRecipe();
        $this->submitEdit($recipe->getSlug(), $this->pngFile('kuchen.png'));
        self::assertCount(1, $this->storedFiles());

        // Submitting the real form carries the real CSRF token.
        $crawler = $this->client->request('GET', '/'.$recipe->getSlug());
        $this->client->submit($crawler->selectButton('Löschen')->form());

        self::assertResponseRedirects('/');
        self::assertSame([], $this->storedFiles());
    }

    private function ownRecipe(): \App\Entity\Recipe
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);

        return $this->createRecipe($owner);
    }

    private function submitEdit(string $slug, UploadedFile $file): void
    {
        $this->client->request('POST', '/'.$slug.'/edit', [
            'recipe' => [
                'title' => 'Käsekuchen',
                'text' => 'Ein Kuchen.',
                'baseServings' => 4,
            ],
        ], [
            'recipe' => ['teaserImage' => $file],
        ]);
    }

    private function pngFile(string $name): UploadedFile
    {
        $path = $this->fixtureDir.'/'.$name;
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);

        return new UploadedFile($path, $name, 'image/png', test: true);
    }

    private function svgFile(string $name, string $clientMimeType): UploadedFile
    {
        $path = $this->fixtureDir.'/'.$name;
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        return new UploadedFile($path, $name, $clientMimeType, test: true);
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        if (!is_dir($this->uploadDir)) {
            return [];
        }

        return array_values(array_diff(scandir($this->uploadDir) ?: [], ['.', '..']));
    }
}
