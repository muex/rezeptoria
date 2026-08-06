<?php

namespace App\Tests\Unit;

use App\Entity\Recipe;
use App\Entity\User;
use App\Security\Voter\RecipeVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * The rules that decide who sees a recipe: an owner may see their own work
 * while it is deactivated, an admin block outranks the owner, and a
 * deactivated account takes its recipes down with it.
 */
final class RecipeVoterTest extends TestCase
{
    /**
     * @param array{active?: bool, blocked?: bool, ownerActive?: bool} $recipeState
     */
    #[DataProvider('viewCases')]
    public function testViewAccess(array $recipeState, ?string $viewer, bool $expected): void
    {
        self::assertSame($expected, $this->allows(RecipeVoter::VIEW, $recipeState, $viewer));
    }

    /**
     * @return iterable<string, array{array<string, bool>, ?string, bool}>
     */
    public static function viewCases(): iterable
    {
        yield 'published, anonymous' => [[], null, true];
        yield 'published, stranger' => [[], 'stranger', true];
        yield 'deactivated, anonymous' => [['active' => false], null, false];
        yield 'deactivated, stranger' => [['active' => false], 'stranger', false];
        yield 'deactivated, owner' => [['active' => false], 'owner', true];
        yield 'deactivated, admin' => [['active' => false], 'admin', true];
        yield 'blocked, owner' => [['blocked' => true], 'owner', true];
        yield 'blocked, stranger' => [['blocked' => true], 'stranger', false];
        yield 'owner account off, stranger' => [['ownerActive' => false], 'stranger', false];
        yield 'owner account off, owner' => [['ownerActive' => false], 'owner', true];
    }

    /**
     * @param array{active?: bool, blocked?: bool, ownerActive?: bool} $recipeState
     */
    #[DataProvider('editCases')]
    public function testEditAndDeleteAccess(array $recipeState, ?string $viewer, bool $expected): void
    {
        self::assertSame($expected, $this->allows(RecipeVoter::EDIT, $recipeState, $viewer));
        self::assertSame($expected, $this->allows(RecipeVoter::DELETE, $recipeState, $viewer));
    }

    /**
     * @return iterable<string, array{array<string, bool>, ?string, bool}>
     */
    public static function editCases(): iterable
    {
        yield 'owner' => [[], 'owner', true];
        yield 'stranger' => [[], 'stranger', false];
        yield 'anonymous' => [[], null, false];
        yield 'admin' => [[], 'admin', true];
    }

    /**
     * The owner switch is the one thing an admin block takes away — otherwise
     * the owner could simply publish a blocked recipe again.
     *
     * @param array{active?: bool, blocked?: bool, ownerActive?: bool} $recipeState
     */
    #[DataProvider('toggleCases')]
    public function testToggleActiveAccess(array $recipeState, ?string $viewer, bool $expected): void
    {
        self::assertSame($expected, $this->allows(RecipeVoter::TOGGLE_ACTIVE, $recipeState, $viewer));
    }

    /**
     * @return iterable<string, array{array<string, bool>, ?string, bool}>
     */
    public static function toggleCases(): iterable
    {
        yield 'owner, not blocked' => [[], 'owner', true];
        yield 'owner, blocked by admin' => [['blocked' => true], 'owner', false];
        yield 'admin, blocked' => [['blocked' => true], 'admin', true];
        yield 'stranger' => [[], 'stranger', false];
    }

    public function testUnrelatedAttributesAreAbstained(): void
    {
        $recipe = $this->recipe([], new User());

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            (new RecipeVoter())->vote($this->token(null, $recipe->getOwner()), $recipe, ['SOMETHING_ELSE'])
        );
    }

    /**
     * @param array{active?: bool, blocked?: bool, ownerActive?: bool} $recipeState
     */
    private function allows(string $attribute, array $recipeState, ?string $viewer): bool
    {
        $owner = $this->user('owner', $recipeState['ownerActive'] ?? true);
        $recipe = $this->recipe($recipeState, $owner);

        $user = match ($viewer) {
            'owner' => $owner,
            'admin' => $this->user('chef', true, admin: true),
            'stranger' => $this->user('stranger'),
            default => null,
        };

        return VoterInterface::ACCESS_GRANTED === (new RecipeVoter())->vote($this->token($user, $owner), $recipe, [$attribute]);
    }

    /**
     * @param array{active?: bool, blocked?: bool, ownerActive?: bool} $state
     */
    private function recipe(array $state, User $owner): Recipe
    {
        $recipe = new Recipe();
        $recipe->setOwner($owner);
        $recipe->setTitle('Käsekuchen');
        $recipe->setActive($state['active'] ?? true);
        $recipe->setBlockedByAdmin($state['blocked'] ?? false);

        return $recipe;
    }

    private function user(string $username, bool $active = true, bool $admin = false): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setActive($active);
        $user->setRoles($admin ? ['ROLE_ADMIN'] : []);

        return $user;
    }

    private function token(?User $user, User $owner): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
