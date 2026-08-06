<?php

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\User;
use App\Tests\Support\DatabaseTestCase;

final class RecipeListingTest extends DatabaseTestCase
{
    /**
     * The listing used to ask every card for its categories and its comment
     * count, so the number of queries grew with the number of recipes. What is
     * asserted here is not a particular number but that it stays flat.
     */
    public function testTheNumberOfQueriesDoesNotGrowWithTheNumberOfRecipes(): void
    {
        $owner = $this->createUser();
        $this->seedRecipes($owner, 3);
        $few = $this->queryCountOfTheListing();

        $this->seedRecipes($owner, 12, offset: 3);
        $many = $this->queryCountOfTheListing();

        self::assertSame($few, $many, sprintf(
            '3 recipes took %d queries, 15 took %d — the listing is querying per recipe again.',
            $few,
            $many
        ));
    }

    public function testTheListingShowsCategoriesAndCommentCounts(): void
    {
        $owner = $this->createUser();
        $this->seedRecipes($owner, 1);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Kuchen', $crawler->filter('article')->first()->text());
        self::assertStringContainsString('2 Kommentare', $crawler->filter('article')->first()->text());
    }

    public function testARecipeWithoutCommentsReadsZero(): void
    {
        $this->createRecipe($this->createUser(), 'Ohne', 'ohne');

        $crawler = $this->client->request('GET', '/');

        self::assertStringContainsString('0 Kommentare', $crawler->filter('article')->first()->text());
    }

    public function testDeactivatedRecipesAreHiddenFromTheListing(): void
    {
        $owner = $this->createUser('owner');
        $this->createRecipe($owner, 'Sichtbar', 'sichtbar');
        $this->createRecipe($owner, 'Versteckt', 'versteckt', active: false);
        $this->createRecipe($owner, 'Gesperrt', 'gesperrt', blockedByAdmin: true);

        $crawler = $this->client->request('GET', '/');
        self::assertCount(1, $crawler->filter('article'));

        // The owner keeps seeing their own, so they can publish them again.
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/');
        self::assertCount(3, $crawler->filter('article'));
    }

    private function queryCountOfTheListing(): int
    {
        // The collector reports every query the connection has run since the
        // last request, fixtures included — one throwaway request clears them.
        $this->client->request('GET', '/');

        $this->client->enableProfiler();
        $this->client->request('GET', '/');

        $profile = $this->client->getProfile();
        self::assertNotFalse($profile, 'the profiler must be enabled in the test environment');

        return $profile->getCollector('db')->getQueryCount();
    }

    private function seedRecipes(User $owner, int $count, int $offset = 0): void
    {
        $category = $this->createCategory('Kuchen');

        for ($i = $offset + 1; $i <= $offset + $count; ++$i) {
            $recipe = $this->createRecipe($owner, "Rezept $i", "rezept-$i");
            $recipe->addCategory($category);
            $author = $recipe->getOwner();

            for ($c = 0; $c < 2; ++$c) {
                $comment = new Comment();
                $comment->setText("Kommentar $c");
                $comment->setCreatedAt(new \DateTimeImmutable());
                $comment->setCreatedUser($author);
                $recipe->addComment($comment);
                $this->entityManager->persist($comment);
            }
        }

        $this->entityManager->flush();
    }
}
