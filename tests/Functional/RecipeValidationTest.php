<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\Response;

final class RecipeValidationTest extends DatabaseTestCase
{
    /**
     * An empty title used to reach setTitle(string) as null and end in a 500
     * before the validator ever saw it.
     */
    public function testEmptyTitleIsRefusedWithAMessage(): void
    {
        $this->submitEdit(['title' => '', 'text' => 'x', 'baseServings' => 4]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.text-red-600', 'Bitte gib dem Rezept einen Titel.');
    }

    public function testOverlongTitleIsRefused(): void
    {
        $this->submitEdit(['title' => str_repeat('T', 300), 'text' => 'x', 'baseServings' => 4]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.text-red-600', 'höchstens 255 Zeichen');
    }

    /**
     * The field is rendered by hand with form_widget, which shows no errors of
     * its own — the form used to come back silent.
     */
    public function testServingsOutOfRangeShowsItsError(): void
    {
        $this->submitEdit(['title' => 'Käsekuchen', 'text' => 'x', 'baseServings' => 0]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.text-red-600', 'Portionszahl zwischen 1 und 100');
    }

    public function testValidSubmissionIsSaved(): void
    {
        $this->submitEdit(['title' => 'Neuer Titel', 'text' => 'x', 'baseServings' => 6]);

        self::assertResponseRedirects();
        $recipe = $this->reloadRecipe('kaesekuchen');
        self::assertSame('Neuer Titel', $recipe->getTitle());
        self::assertSame(6, $recipe->getBaseServings());
    }

    /**
     * Renaming leaves the address alone, which is the whole point of storing
     * the slug instead of deriving it on the fly.
     */
    public function testRenamingDoesNotMoveTheRecipe(): void
    {
        $this->submitEdit(['title' => 'Ganz anderer Titel', 'text' => 'x', 'baseServings' => 4]);

        self::assertSame('kaesekuchen', $this->reloadRecipe('kaesekuchen')->getSlug());

        $this->client->request('GET', '/kaesekuchen');
        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, scalar> $fields
     */
    private function submitEdit(array $fields): void
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);
        $this->createRecipe($owner);

        $this->client->request('POST', '/kaesekuchen/edit', ['recipe' => $fields]);
    }
}
