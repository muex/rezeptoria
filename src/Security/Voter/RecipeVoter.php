<?php

namespace App\Security\Voter;

use App\Entity\Recipe;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Recipe>
 */
final class RecipeVoter extends Voter
{
    public const string VIEW = 'RECIPE_VIEW';
    public const string EDIT = 'RECIPE_EDIT';
    public const string DELETE = 'RECIPE_DELETE';
    public const string TOGGLE_ACTIVE = 'RECIPE_TOGGLE_ACTIVE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Recipe
            && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::TOGGLE_ACTIVE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Recipe $recipe */
        $recipe = $subject;
        $user = $token->getUser();

        if ($user instanceof User && $user->isAdmin()) {
            return true;
        }

        $isOwner = $user instanceof User && $recipe->getOwner() === $user;

        return match ($attribute) {
            self::VIEW => $recipe->isPubliclyVisible() || $isOwner,
            self::EDIT, self::DELETE => $isOwner,
            // An admin block cannot be lifted by the owner.
            self::TOGGLE_ACTIVE => $isOwner && !$recipe->isBlockedByAdmin(),
            default => false,
        };
    }
}
