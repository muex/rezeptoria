<?php

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CommentTest extends DatabaseTestCase
{
    public function testACommentIsSavedAndTheVisitorReturnsToTheRecipe(): void
    {
        $this->loggedInOnARecipe();

        $this->client->request('POST', '/kaesekuchen/comment/new', ['comment' => ['text' => 'Sehr lecker!']]);

        self::assertResponseRedirects('/kaesekuchen');
        self::assertCount(1, $this->entityManager->getRepository(Comment::class)->findAll());
    }

    /**
     * An invalid comment used to be dropped without a word.
     */
    public function testAnEmptyCommentComesBackWithAMessage(): void
    {
        $this->loggedInOnARecipe();

        $this->client->request('POST', '/kaesekuchen/comment/new', ['comment' => ['text' => '']]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.text-red-600', 'Bitte schreib etwas');
        self::assertCount(0, $this->entityManager->getRepository(Comment::class)->findAll());
    }

    public function testARefusedCommentKeepsItsText(): void
    {
        $this->loggedInOnARecipe();
        $tooLong = str_repeat('a', 2001);

        $crawler = $this->client->request('POST', '/kaesekuchen/comment/new', ['comment' => ['text' => $tooLong]]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('.text-red-600', 'höchstens 2000 Zeichen');
        self::assertSame($tooLong, $crawler->filter('#comment-form textarea')->text());
    }

    public function testAnonymousVisitorsCannotComment(): void
    {
        $this->createRecipe($this->createUser());

        $this->client->request('POST', '/kaesekuchen/comment/new', ['comment' => ['text' => 'Hallo']]);

        self::assertResponseRedirects('http://localhost/login');
        self::assertCount(0, $this->entityManager->getRepository(Comment::class)->findAll());
    }

    public function testCommentsOnAHiddenRecipeAreRefused(): void
    {
        $this->createRecipe($this->createUser('owner'), active: false);
        $this->client->loginUser($this->createUser('stranger'));

        $this->client->request('POST', '/kaesekuchen/comment/new', ['comment' => ['text' => 'Hallo']]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function loggedInOnARecipe(): void
    {
        $owner = $this->createUser();
        $this->createRecipe($owner);
        $this->client->loginUser($owner);
    }
}
