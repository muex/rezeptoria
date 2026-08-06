<?php

namespace App\Tests\Unit;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Service\RecipeSlugGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecipeSlugGeneratorTest extends TestCase
{
    #[DataProvider('titles')]
    public function testBaseSlugIsBuiltFromTheTitle(?string $title, string $expected): void
    {
        self::assertSame($expected, RecipeSlugGenerator::baseSlug($title));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function titles(): iterable
    {
        // German transliteration, not the app locale's — "Äpfel" must not
        // become "apfel" in a URL people read.
        yield 'umlauts' => ['Käsekuchen mit Äpfeln', 'kaesekuchen-mit-aepfeln'];
        yield 'sharp s' => ['Grüße & Soße', 'gruesse-sosse'];
        yield 'accents' => ['Crème brûlée', 'creme-brulee'];
        yield 'leading digits stay' => ['123 Grießbrei', '123-griessbrei'];
        yield 'punctuation only' => ['  ---  ', 'rezept'];
        yield 'empty' => ['', 'rezept'];
        yield 'null' => [null, 'rezept'];
    }

    public function testLongTitleIsTruncatedWithRoomForASuffix(): void
    {
        $slug = RecipeSlugGenerator::baseSlug(str_repeat('sehr langer titel ', 40));

        self::assertLessThanOrEqual(240, strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
    }

    public function testFreeSlugIsUsedAsIs(): void
    {
        $generator = new RecipeSlugGenerator($this->repositoryReturning([]));

        self::assertSame('kaesekuchen', $generator->generate($this->recipe('Käsekuchen')));
    }

    public function testTakenSlugGetsACountingSuffix(): void
    {
        $taken = ['kaesekuchen' => new Recipe(), 'kaesekuchen-2' => new Recipe()];
        $generator = new RecipeSlugGenerator($this->repositoryReturning($taken));

        self::assertSame('kaesekuchen-3', $generator->generate($this->recipe('Käsekuchen')));
    }

    public function testARecipeDoesNotCollideWithItself(): void
    {
        $recipe = $this->recipe('Käsekuchen');
        $generator = new RecipeSlugGenerator($this->repositoryReturning(['kaesekuchen' => $recipe]));

        self::assertSame('kaesekuchen', $generator->generate($recipe));
    }

    /**
     * A recipe titled "Login" must not claim /login, which the router would
     * never route to it anyway — the page would simply be unreachable.
     */
    #[DataProvider('reservedWords')]
    public function testReservedWordsAreNeverHandedOut(string $title, string $expected): void
    {
        $generator = new RecipeSlugGenerator($this->repositoryReturning([]));

        self::assertSame($expected, $generator->generate($this->recipe($title)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function reservedWords(): iterable
    {
        yield 'login' => ['Login', 'login-2'];
        yield 'admin' => ['Admin', 'admin-2'];
        yield 'reset password' => ['Reset Password', 'reset-password-2'];
        yield 'not reserved as prefix' => ['Login Kuchen', 'login-kuchen'];
    }

    /**
     * @param array<string, Recipe> $bySlug
     */
    private function repositoryReturning(array $bySlug): RecipeRepository
    {
        $repository = $this->createStub(RecipeRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?Recipe => $bySlug[$criteria['slug']] ?? null
        );

        return $repository;
    }

    private function recipe(string $title): Recipe
    {
        $recipe = new Recipe();
        $recipe->setTitle($title);

        return $recipe;
    }
}
