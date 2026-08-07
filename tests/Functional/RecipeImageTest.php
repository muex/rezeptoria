<?php

namespace App\Tests\Functional;

use App\Tests\Support\ImageUploadTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * The teaser image: uploads are served from our own origin, so what lands in
 * the upload directory has to be a real image — and nothing may stay there
 * once no recipe points at it.
 */
final class RecipeImageTest extends ImageUploadTestCase
{
    public function testAnSvgIsRejected(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitTeaser($recipe->getSlug(), $this->svgFile('schaedlich.svg', 'image/svg+xml'));

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

        $this->submitTeaser($recipe->getSlug(), $this->svgFile('harmlos.png', 'image/png'));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([], $this->storedFiles());
    }

    public function testAValidImageIsStored(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitTeaser($recipe->getSlug(), $this->pngFile('kuchen.png'));

        self::assertResponseRedirects();
        self::assertCount(1, $this->storedFiles());

        $stored = $this->reloadRecipe($recipe->getSlug())->getTeaserImage();
        self::assertNotNull($stored);
        self::assertContains($stored, $this->storedFiles());
    }

    public function testReplacingTheImageRemovesTheOldFile(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitTeaser($recipe->getSlug(), $this->pngFile('erstes.png'));
        $first = $this->reloadRecipe($recipe->getSlug())->getTeaserImage();

        $this->submitTeaser($recipe->getSlug(), $this->pngFile('zweites.png'));
        $second = $this->reloadRecipe($recipe->getSlug())->getTeaserImage();

        self::assertNotSame($first, $second);
        self::assertSame([$second], $this->storedFiles(), 'the replaced file should be gone');
    }

    public function testTickingTheBoxTakesTheImageAway(): void
    {
        $recipe = $this->ownRecipe();
        $this->submitTeaser($recipe->getSlug(), $this->pngFile('kuchen.png'));
        self::assertCount(1, $this->storedFiles());

        $this->submitEdit($recipe->getSlug(), ['removeTeaserImage' => '1']);

        self::assertResponseRedirects();
        self::assertNull($this->reloadRecipe($recipe->getSlug())->getTeaserImage());
        self::assertSame([], $this->storedFiles());
    }

    /**
     * Picking a file says more clearly what the image should be than a box
     * left ticked from before.
     */
    public function testAFreshUploadWinsOverTheRemoveBox(): void
    {
        $recipe = $this->ownRecipe();
        $this->submitTeaser($recipe->getSlug(), $this->pngFile('erstes.png'));

        $this->submitEdit(
            $recipe->getSlug(),
            ['removeTeaserImage' => '1'],
            ['teaserImage' => $this->pngFile('zweites.png')],
        );

        $stored = $this->reloadRecipe($recipe->getSlug())->getTeaserImage();
        self::assertNotNull($stored);
        self::assertSame([$stored], $this->storedFiles());
    }

    /**
     * The box is only worth showing once there is something to take away.
     */
    public function testTheRemoveBoxOnlyAppearsWithAnImage(): void
    {
        $recipe = $this->ownRecipe();

        $crawler = $this->client->request('GET', '/'.$recipe->getSlug().'/edit');
        self::assertCount(0, $crawler->filter('input[name="recipe[removeTeaserImage]"]'));

        $this->submitTeaser($recipe->getSlug(), $this->pngFile('kuchen.png'));

        $crawler = $this->client->request('GET', '/'.$recipe->getSlug().'/edit');
        self::assertCount(1, $crawler->filter('input[name="recipe[removeTeaserImage]"]'));
    }

    public function testDeletingTheRecipeRemovesItsImage(): void
    {
        $recipe = $this->ownRecipe();
        $this->submitTeaser($recipe->getSlug(), $this->pngFile('kuchen.png'));
        self::assertCount(1, $this->storedFiles());

        // Submitting the real form carries the real CSRF token.
        $crawler = $this->client->request('GET', '/'.$recipe->getSlug());
        $this->client->submit($crawler->selectButton('Löschen')->form());

        self::assertResponseRedirects('/');
        self::assertSame([], $this->storedFiles());
    }

    private function submitTeaser(string $slug, UploadedFile $file): void
    {
        $this->submitEdit($slug, [], ['teaserImage' => $file]);
    }
}
