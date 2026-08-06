<?php

namespace App\Tests\Functional;

use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recipes sit directly below the root, so the slug route is one careless
 * pattern away from swallowing /login, /register or /reset-password.
 */
final class RecipeRoutingTest extends DatabaseTestCase
{
    public function testRecipeIsServedUnderItsSlug(): void
    {
        $this->createRecipe($this->createUser(), 'Käsekuchen mit Äpfeln', 'kaesekuchen-mit-aepfeln');

        $crawler = $this->client->request('GET', '/kaesekuchen-mit-aepfeln');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Käsekuchen mit Äpfeln');
        self::assertStringNotContainsString('/recipe/', $crawler->filter('h1')->html());
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $this->client->request('GET', '/gibt-es-nicht');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[DataProvider('reservedPaths')]
    public function testReservedPathsAreNotSwallowedByTheSlugRoute(string $path, string $expectedRoute): void
    {
        // A recipe on that exact slug must not be able to take the path over —
        // it cannot exist, but the router must not depend on that.
        $this->client->request('GET', $path);

        self::assertSame($expectedRoute, $this->client->getRequest()->attributes->get('_route'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function reservedPaths(): iterable
    {
        yield 'login' => ['/login', 'app_login'];
        yield 'register' => ['/register', 'app_register'];
        yield 'reset password' => ['/reset-password', 'app_forgot_password_request'];
        yield 'new recipe' => ['/recipe/new', 'app_recipe_new'];
        yield 'index' => ['/', 'app_recipe_index'];
    }

    public function testSlugMayStartWithAReservedWord(): void
    {
        $this->createRecipe($this->createUser(), 'Login Kuchen', 'loginkuchen');

        $this->client->request('GET', '/loginkuchen');

        self::assertResponseIsSuccessful();
        self::assertSame('app_recipe_show', $this->client->getRequest()->attributes->get('_route'));
    }

    public function testOldIdUrlRedirectsPermanentlyToTheSlug(): void
    {
        $recipe = $this->createRecipe($this->createUser(), 'Käsekuchen', 'kaesekuchen');

        $this->client->request('GET', '/recipe/'.$recipe->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        self::assertResponseRedirects('/kaesekuchen');
    }

    public function testOldIdUrlOfAHiddenRecipeDoesNotLeakItsSlug(): void
    {
        $recipe = $this->createRecipe($this->createUser(), 'Geheim', 'geheim', active: false);

        $this->client->request('GET', '/recipe/'.$recipe->getId());

        // Anonymous visitors are sent to the login form, not to the slug.
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testDeactivatedRecipeStaysVisibleForItsOwner(): void
    {
        $owner = $this->createUser();
        $this->createRecipe($owner, 'Geheim', 'geheim', active: false);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/geheim');

        self::assertResponseIsSuccessful();
    }

    public function testDeactivatedRecipeIsHiddenFromOtherUsers(): void
    {
        $this->createRecipe($this->createUser('owner'), 'Geheim', 'geheim', active: false);

        $this->client->loginUser($this->createUser('someone-else'));
        $this->client->request('GET', '/geheim');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
