<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\User;
use App\Form\CommentType;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use App\Security\Voter\RecipeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/')]
final class RecipeController extends AbstractController
{
    #[Route('/', name: 'app_recipe_index', methods: ['GET'])]
    public function index(RecipeRepository $recipeRepository): Response
    {
        $viewer = $this->getUser();

        return $this->render('recipe/index.html.twig', [
            'recipes' => $recipeRepository->findVisibleFor($viewer instanceof User ? $viewer : null),
        ]);
    }

    #[Route('/recipe/new', name: 'app_recipe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $recipe = new Recipe();
        $user = $this->getUser();

        $recipe->setOwner($user);

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $teaserImageFile = $form->get('teaserImage')->getData();
            if ($teaserImageFile) {
                $originalFilename = pathinfo($teaserImageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$teaserImageFile->guessExtension();

                try {
                    $teaserImageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle Exception
                }

                $recipe->setTeaserImage($newFilename);
            }

            $entityManager->persist($recipe);
            $entityManager->flush();

            return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recipe/new.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    #[Route('/recipe/{id}', name: 'app_recipe_show', methods: ['GET'])]
    #[IsGranted(RecipeVoter::VIEW, subject: 'recipe')]
    public function show(Recipe $recipe, Request $request, EntityManagerInterface $entityManager): Response
    {

        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe
        ]);
    }

    /**
     * Owner switch between published and hidden. Admin-blocked recipes are
     * refused by the voter, so the owner cannot undo a block.
     */
    #[Route('/recipe/{id}/toggle-active', name: 'app_recipe_toggle_active', methods: ['POST'])]
    #[IsGranted(RecipeVoter::TOGGLE_ACTIVE, subject: 'recipe')]
    public function toggleActive(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle-active'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            $recipe->setActive(!$recipe->isActive());
            $entityManager->flush();

            $this->addFlash('success', $recipe->isActive()
                ? 'Rezept ist jetzt für alle sichtbar.'
                : 'Rezept ist jetzt deaktiviert und nur noch für dich sichtbar.');
        }

        return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/recipe/{id}/edit', name: 'app_recipe_edit', methods: ['GET', 'POST'])]
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
    public function edit(Request $request, Recipe $recipe, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $teaserImageFile = $form->get('teaserImage')->getData();
            if ($teaserImageFile) {
                $originalFilename = pathinfo($teaserImageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$teaserImageFile->guessExtension();

                try {
                    $teaserImageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Handle Exception
                }

                $recipe->setTeaserImage($newFilename);
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    #[Route('/recipe/{id}', name: 'app_recipe_delete', methods: ['POST'])]
    #[IsGranted(RecipeVoter::DELETE, subject: 'recipe')]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($recipe);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/recipe/{id}/comment/new', name: 'app_recipe_comment_new', methods: ['GET', 'POST'])]
    #[IsGranted(RecipeVoter::VIEW, subject: 'recipe')]
    public function newComment(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $user = $this->getUser();
            $recipe->addComment($comment);
            $user->addComment($comment);
            $comment->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($comment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_recipe_show', ['id' => $recipe->getId()]);
    }

    public function commentForm(Recipe $recipe): Response
    {
        $form = $this->createForm(CommentType::class);

        return $this->render('recipe/_comment_form.html.twig', [
            'form' => $form->createView(),
            'recipe' => $recipe,
        ]);
    }
}
