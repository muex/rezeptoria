<?php

namespace App\Tests\Functional;

use App\Entity\RecipeImage;
use App\Tests\Support\ImageUploadTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * The images beyond the teaser: the recipe's gallery and the one picture a
 * section may carry. They go through the same upload rules as the teaser, and
 * the same promise holds — no file stays behind once the recipe stops pointing
 * at it.
 */
final class RecipeGalleryTest extends ImageUploadTestCase
{
    public function testGalleryImagesAreStoredInTheSubmittedOrder(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), [
            'images' => [
                ['caption' => 'Der Teig'],
                ['caption' => 'Fertig gebacken'],
            ],
        ], [
            'images' => [
                ['file' => $this->pngFile('eins.png')],
                ['file' => $this->pngFile('zwei.png')],
            ],
        ]);

        self::assertResponseRedirects();

        $images = $this->galleryOf($recipe->getSlug());
        self::assertCount(2, $images);
        self::assertSame(['Der Teig', 'Fertig gebacken'], array_map(static fn (RecipeImage $i): ?string => $i->getCaption(), $images));
        self::assertSame([0, 1], array_map(static fn (RecipeImage $i): int => $i->getPosition(), $images));
        self::assertCount(2, $this->storedFiles());
    }

    public function testTheGalleryIsShownOnTheRecipePage(): void
    {
        $recipe = $this->ownRecipe();
        $this->submitEdit($recipe->getSlug(), [
            'images' => [['caption' => 'Der Teig']],
        ], [
            'images' => [['file' => $this->pngFile('eins.png')]],
        ]);

        $crawler = $this->client->request('GET', '/'.$recipe->getSlug());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.gallery-image'));
        self::assertSame('Der Teig', $crawler->filter('.gallery-image')->attr('data-caption'));
    }

    /**
     * The teaser has a job of its own — it must not be pulled into the gallery.
     */
    public function testTheTeaserIsNotPartOfTheGallery(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), [], ['teaserImage' => $this->pngFile('titel.png')]);

        self::assertSame([], $this->galleryOf($recipe->getSlug()));
    }

    public function testRemovingAGalleryImageDeletesItsFile(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), [
            'images' => [['caption' => 'Eins'], ['caption' => 'Zwei']],
        ], [
            'images' => [
                ['file' => $this->pngFile('eins.png')],
                ['file' => $this->pngFile('zwei.png')],
            ],
        ]);

        $stored = $this->galleryOf($recipe->getSlug());
        $kept = $stored[1]->getFilename();

        // Only the second row comes back — the first one was removed in the
        // browser, which is how allow_delete learns that it is gone.
        $this->submitEdit($recipe->getSlug(), ['images' => [1 => ['caption' => 'Zwei']]]);

        self::assertResponseRedirects();
        self::assertCount(1, $this->galleryOf($recipe->getSlug()));
        self::assertSame([$kept], $this->storedFiles(), 'the removed image should be gone from disk');
    }

    /**
     * Clicking "+ Bild hinzufügen" and then picking nothing must not save an
     * image row that has no file behind it.
     */
    public function testARowWithoutAFileIsDropped(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), ['images' => [['caption' => 'Noch kein Bild']]]);

        self::assertResponseRedirects();
        self::assertSame([], $this->galleryOf($recipe->getSlug()));
        self::assertSame([], $this->storedFiles());
    }

    public function testAnSvgInTheGalleryIsRejected(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), [
            'images' => [['caption' => '']],
        ], [
            'images' => [['file' => $this->svgFile('schaedlich.svg', 'image/svg+xml')]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.text-red-600', 'Bitte lade ein Bild im Format JPG, PNG oder WebP hoch.');
        self::assertSame([], $this->storedFiles());
    }

    public function testASectionImageIsStoredAndShown(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitSection($recipe->getSlug(), $this->pngFile('teig.png'));

        self::assertResponseRedirects();

        $section = $this->reloadRecipe($recipe->getSlug())->getSections()->first();
        self::assertNotFalse($section);
        self::assertNotNull($section->getImage());

        $crawler = $this->client->request('GET', '/'.$recipe->getSlug());
        self::assertCount(1, $crawler->filter('img[src*="'.$section->getImage().'"]'));
    }

    public function testReplacingASectionImageRemovesTheOldFile(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitSection($recipe->getSlug(), $this->pngFile('erstes.png'));
        $first = $this->reloadRecipe($recipe->getSlug())->getSections()->first();
        self::assertNotFalse($first);

        // The same index maps onto the same section, so this replaces its image.
        $this->submitSection($recipe->getSlug(), $this->pngFile('zweites.png'));
        $second = $this->reloadRecipe($recipe->getSlug())->getSections()->first();
        self::assertNotFalse($second);

        self::assertNotSame($first->getImage(), $second->getImage());
        self::assertSame([$second->getImage()], $this->storedFiles());
    }

    public function testTickingTheBoxTakesASectionImageAway(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitSection($recipe->getSlug(), $this->pngFile('teig.png'));
        self::assertCount(1, $this->storedFiles());

        $this->submitEdit($recipe->getSlug(), [
            'sections' => [['title' => 'Teig', 'preparation' => 'Rühren', 'removeImage' => '1']],
        ]);

        self::assertResponseRedirects();

        $section = $this->reloadRecipe($recipe->getSlug())->getSections()->first();
        self::assertNotFalse($section);
        self::assertNull($section->getImage());
        self::assertSame([], $this->storedFiles());
    }

    public function testAnSvgForASectionIsRejected(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitSection($recipe->getSlug(), $this->svgFile('schaedlich.svg', 'image/svg+xml'));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([], $this->storedFiles());
    }

    /**
     * The edit form draws every image the recipe already has next to its
     * fields, so it has to survive being rendered with them.
     */
    public function testTheEditFormShowsTheImagesTheRecipeAlreadyHas(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), [
            'images' => [['caption' => 'Der Teig']],
            'sections' => [['title' => 'Teig', 'preparation' => 'Rühren']],
        ], [
            'images' => [['file' => $this->pngFile('eins.png')]],
            'sections' => [['image' => $this->pngFile('teig.png')]],
        ]);

        $crawler = $this->client->request('GET', '/'.$recipe->getSlug().'/edit');

        self::assertResponseIsSuccessful();
        // Scoped to the list: the <template> for new rows carries the same class.
        self::assertCount(1, $crawler->filter('#gallery-list .gallery-item'));
        self::assertSame('Der Teig', $crawler->filter('input[name="recipe[images][0][caption]"]')->attr('value'));
        self::assertCount(1, $crawler->filter('input[name="recipe[images][0][file]"]'));
        self::assertCount(1, $crawler->filter('input[name="recipe[sections][0][image]"]'));
    }

    public function testDeletingTheRecipeRemovesEveryImageItHad(): void
    {
        $recipe = $this->ownRecipe();

        $this->submitEdit($recipe->getSlug(), [
            'images' => [['caption' => 'Eins']],
            'sections' => [['title' => 'Teig', 'preparation' => 'Rühren']],
        ], [
            'teaserImage' => $this->pngFile('titel.png'),
            'images' => [['file' => $this->pngFile('eins.png')]],
            'sections' => [['image' => $this->pngFile('teig.png')]],
        ]);

        self::assertCount(3, $this->storedFiles(), 'teaser, gallery image and section image');

        $crawler = $this->client->request('GET', '/'.$recipe->getSlug());
        $this->client->submit($crawler->selectButton('Löschen')->form());

        self::assertResponseRedirects('/');
        self::assertSame([], $this->storedFiles());
    }

    /**
     * @return list<RecipeImage>
     */
    private function galleryOf(string $slug): array
    {
        return array_values($this->reloadRecipe($slug)->getImages()->toArray());
    }

    private function submitSection(string $slug, UploadedFile $file): void
    {
        $this->submitEdit(
            $slug,
            ['sections' => [['title' => 'Teig', 'preparation' => 'Rühren']]],
            ['sections' => [['image' => $file]]],
        );
    }
}
