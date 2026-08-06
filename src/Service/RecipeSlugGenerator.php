<?php

namespace App\Service;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Builds the URL identifier of a recipe. Recipes live directly below the root
 * (/kaesekuchen), so a slug must neither collide with another recipe nor with
 * one of the fixed top-level paths.
 */
final class RecipeSlugGenerator
{
    /**
     * Top-level paths that are not recipes. Kept in sync with SLUG_PATTERN,
     * which keeps the router from matching them as a recipe in the first place.
     *
     * @var list<string>
     */
    public const array RESERVED = ['admin', 'category', 'login', 'logout', 'recipe', 'register', 'reset-password'];

    /**
     * Route requirement for the {slug} parameter: lower-case, no leading
     * underscore (that would shadow /_profiler & co.) and none of RESERVED.
     */
    public const string SLUG_PATTERN = '(?!(?:admin|category|login|logout|recipe|register|reset-password)(?![a-z0-9-]))[a-z0-9][a-z0-9-]*';

    /** Leaves room in the 255 char column for the "-2", "-3", … suffix. */
    private const int MAX_BASE_LENGTH = 240;

    public function __construct(private readonly RecipeRepository $recipeRepository)
    {
    }

    /**
     * Slug of the title alone — no uniqueness check. Shared with the migration
     * that backfills existing recipes, so both produce the same URLs.
     */
    public static function baseSlug(?string $title): string
    {
        // Pinned to German so umlauts become "ae"/"oe"/"ue" instead of "a"/"o"/"u",
        // independent of the app locale.
        $slug = new AsciiSlugger('de')->slug((string) $title)->lower()->toString();
        $slug = trim(substr($slug, 0, self::MAX_BASE_LENGTH), '-');

        if ('' === $slug || !preg_match('/^[a-z0-9]/', $slug)) {
            $slug = 'rezept'.('' === $slug ? '' : '-'.$slug);
        }

        return $slug;
    }

    public function generate(Recipe $recipe): string
    {
        $base = self::baseSlug($recipe->getTitle());

        $slug = $base;
        for ($suffix = 2; $this->isTaken($slug, $recipe); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function isTaken(string $slug, Recipe $recipe): bool
    {
        if (in_array($slug, self::RESERVED, true)) {
            return true;
        }

        $owner = $this->recipeRepository->findOneBy(['slug' => $slug]);

        return null !== $owner && $owner !== $recipe;
    }
}
