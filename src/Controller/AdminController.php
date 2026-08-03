<?php

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Repository\RecipeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(UserRepository $userRepository, RecipeRepository $recipeRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'user_count' => $userRepository->count([]),
            'inactive_user_count' => $userRepository->countInactive(),
            'recipe_count' => $recipeRepository->count([]),
            'inactive_recipe_count' => $recipeRepository->count(['active' => false]),
            'blocked_recipe_count' => $recipeRepository->countBlocked(),
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findAllForAdmin(),
        ]);
    }

    #[Route('/users/{id}/toggle', name: 'app_admin_user_toggle', methods: ['POST'])]
    public function toggleUser(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('toggle-user'.$user->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_admin_users', [], Response::HTTP_SEE_OTHER);
        }

        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Du kannst dein eigenes Konto nicht deaktivieren.');

            return $this->redirectToRoute('app_admin_users', [], Response::HTTP_SEE_OTHER);
        }

        $user->setActive(!$user->isActive());
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Benutzer "%s" wurde %s.',
            $user->getUsername(),
            $user->isActive() ? 'aktiviert' : 'deaktiviert'
        ));

        return $this->redirectToRoute('app_admin_users', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/recipes', name: 'app_admin_recipes', methods: ['GET'])]
    public function recipes(RecipeRepository $recipeRepository): Response
    {
        return $this->render('admin/recipes.html.twig', [
            'recipes' => $recipeRepository->findAllForAdmin(),
        ]);
    }

    /**
     * Sets the admin block. The owner's own active flag stays untouched, so
     * unblocking restores whatever the owner had chosen before.
     */
    #[Route('/recipes/{id}/toggle', name: 'app_admin_recipe_toggle', methods: ['POST'])]
    public function toggleRecipe(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle-recipe'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            $recipe->setBlockedByAdmin(!$recipe->isBlockedByAdmin());
            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Rezept "%s" wurde %s.',
                $recipe->getTitle(),
                $recipe->isBlockedByAdmin() ? 'gesperrt' : 'freigegeben'
            ));
        }

        return $this->redirectToRoute('app_admin_recipes', [], Response::HTTP_SEE_OTHER);
    }
}
