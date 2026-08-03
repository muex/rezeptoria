<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Keeps deactivated accounts out — both on login and when an already
 * authenticated session is refreshed from the database.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Dein Konto wurde deaktiviert.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $this->checkPreAuth($user);
    }
}
