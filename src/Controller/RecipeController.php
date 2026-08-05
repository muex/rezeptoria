<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\User;
use App\Form\CommentType;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use App\Security\Voter\RecipeVoter;
use App\Service\RecipeImageStorage;
use App\Service\RecipeSlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/')]
final class RecipeController extends AbstractController
{
    #[Route('/', name: 'app_recipe_index', methods: ['GET'])]
    public function index(RecipeRepository $recipeRepository): Response
    {
        $viewer = $this->getUser();
        $recipes = $recipeRepository->findVisibleFor($viewer instanceof User ? $viewer : null);

        return $this->render('recipe/index.html.twig', [
            'recipes' => $recipes,
            'comment_counts' => $recipeRepository->countCommentsFor($recipes),
        ]);
    }

    #[Route('/recipe/new', name: 'app_recipe_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(#[CurrentUser] User $user, Request $request, EntityManagerInterface $entityManager, RecipeImageStorage $imageStorage, RecipeSlugGenerator $slugGenerator): Response
    {
        $recipe = new Recipe();

        $recipe->setOwner($user);

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $teaserImageFile = $form->get('teaserImage')->getData();
            if ($teaserImageFile instanceof UploadedFile) {
                try {
                    $recipe->setTeaserImage($imageStorage->store($teaserImageFile));
                } catch (FileException) {
                    // Save the recipe without an image rather than pointing at a
                    // file that was never written.
                    $this->addFlash('error', 'Das Bild konnte nicht gespeichert werden.');
                }
            }

            $recipe->setSlug($slugGenerator->generate($recipe));

            $entityManager->persist($recipe);
            $entityManager->flush();

            return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recipe/new.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    /**
     * Recipes lived at /recipe/{id} until the slugs arrived. Keeps links and
     * bookmarks from that time alive.
     */
    #[Route('/recipe/{id}', name: 'app_recipe_show_legacy', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(RecipeVoter::VIEW, subject: 'recipe')]
    public function showLegacy(Recipe $recipe): Response
    {
        return $this->redirectToRoute('app_recipe_show', ['slug' => $recipe->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/{slug}', name: 'app_recipe_show', requirements: ['slug' => RecipeSlugGenerator::SLUG_PATTERN], methods: ['GET'])]
    #[IsGranted(RecipeVoter::VIEW, subject: 'recipe')]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Recipe $recipe, Request $request, EntityManagerInterface $entityManager): Response
    {

        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe
        ]);
    }

    /**
     * Owner switch between published and hidden. Admin-blocked recipes are
     * refused by the voter, so the owner cannot undo a block.
     */
    #[Route('/{slug}/toggle-active', name: 'app_recipe_toggle_active', requirements: ['slug' => RecipeSlugGenerator::SLUG_PATTERN], methods: ['POST'])]
    #[IsGranted(RecipeVoter::TOGGLE_ACTIVE, subject: 'recipe')]
    public function toggleActive(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle-active'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            $recipe->setActive(!$recipe->isActive());
            $entityManager->flush();

            $this->addFlash('success', $recipe->isActive()
                ? 'Rezept ist jetzt für alle sichtbar.'
                : 'Rezept ist jetzt deaktiviert und nur noch für dich sichtbar.');
        }

        return $this->redirectToRoute('app_recipe_show', ['slug' => $recipe->getSlug()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{slug}/edit', name: 'app_recipe_edit', requirements: ['slug' => RecipeSlugGenerator::SLUG_PATTERN], methods: ['GET', 'POST'])]
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Recipe $recipe, EntityManagerInterface $entityManager, RecipeImageStorage $imageStorage): Response
    {
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $teaserImageFile = $form->get('teaserImage')->getData();
            if ($teaserImageFile instanceof UploadedFile) {
                $replacedImage = $recipe->getTeaserImage();

                try {
                    $recipe->setTeaserImage($imageStorage->store($teaserImageFile));
                    // Only once the replacement is safely on disk.
                    $imageStorage->remove($replacedImage);
                } catch (FileException) {
                    // Keep the previous image rather than pointing at a file that was never written.
                    $this->addFlash('error', 'Das Bild konnte nicht gespeichert werden.');
                }
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
            'form' => $form,
        ]);
    }

    #[Route('/{slug}', name: 'app_recipe_delete', requirements: ['slug' => RecipeSlugGenerator::SLUG_PATTERN], methods: ['POST'])]
    #[IsGranted(RecipeVoter::DELETE, subject: 'recipe')]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Recipe $recipe, EntityManagerInterface $entityManager, RecipeImageStorage $imageStorage): Response
    {
        if ($this->isCsrfTokenValid('delete'.$recipe->getId(), $request->getPayload()->getString('_token'))) {
            $teaserImage = $recipe->getTeaserImage();

            $entityManager->remove($recipe);
            $entityManager->flush();

            // After the row is gone, so a failed delete cannot leave a recipe
            // pointing at a missing file.
            $imageStorage->remove($teaserImage);
        }

        return $this->redirectToRoute('app_recipe_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{slug}/comment/new', name: 'app_recipe_comment_new', requirements: ['slug' => RecipeSlugGenerator::SLUG_PATTERN], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(RecipeVoter::VIEW, subject: 'recipe')]
    public function newComment(#[CurrentUser] User $user, Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);

        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            // Show the recipe again with this form instead of the empty one, so
            // the comment and the reason it was refused stay on screen.
            return $this->render('recipe/show.html.twig', [
                'recipe' => $recipe,
                'commentForm' => $form,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $recipe->addComment($comment);
        $user->addComment($comment);
        $comment->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->redirectToRoute('app_recipe_show', ['slug' => $recipe->getSlug()]);
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
