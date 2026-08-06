<?php

namespace App\Tests\Support;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base for tests that need a database. The schema is rebuilt per test, so no
 * test can be influenced by what another one left behind.
 */
abstract class DatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function createUser(string $username = 'koch', string $plainPassword = 'geheim123', ?string $email = null, bool $active = true, bool $admin = false): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setActive($active);
        $user->setRoles($admin ? ['ROLE_ADMIN'] : []);
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $plainPassword)
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function createRecipe(User $owner, string $title = 'Käsekuchen', string $slug = 'kaesekuchen', bool $active = true, bool $blockedByAdmin = false, ?string $teaserImage = null): Recipe
    {
        $recipe = new Recipe();
        // A request of its own detaches whatever the test created before it,
        // so the owner has to be picked up from the current unit of work.
        $recipe->setOwner($this->entityManager->find(User::class, $owner->getId()) ?? $owner);
        $recipe->setTitle($title);
        $recipe->setSlug($slug);
        $recipe->setText('Ein Kuchen.');
        $recipe->setActive($active);
        $recipe->setBlockedByAdmin($blockedByAdmin);
        $recipe->setTeaserImage($teaserImage);

        $this->entityManager->persist($recipe);
        $this->entityManager->flush();

        return $recipe;
    }

    /**
     * Reads a recipe back from the database. A request of its own leaves the
     * entities the test created detached, so they must not be reused.
     */
    protected function reloadRecipe(string $slug): Recipe
    {
        $this->entityManager->clear();

        $recipe = $this->entityManager->getRepository(Recipe::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Recipe::class, $recipe, sprintf('No recipe with slug "%s".', $slug));

        return $recipe;
    }

    protected function createCategory(string $name): Category
    {
        $category = new Category();
        $category->setName($name);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }
}
