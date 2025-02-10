<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Form\CommentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

final class CommentController extends AbstractController
{
    #[Route('/recipe/{id}/comment', name: 'recipe_comment', methods: ['POST'])]
    public function addComment(Request $request, Recipe $recipe, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $comment = new Comment();
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new AccessDeniedException('Nur angemeldete Benutzer können Kommentare schreiben.');
            }

            $comment->setUser($user);
            $comment->setRecipe($recipe);
            $comment->setCreatedAt(new \DateTime());
            $entityManager->persist($comment);
            $entityManager->flush();

            return $this->redirectToRoute('recipe_show', ['id' => $recipe->getId()]);
        }

        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe,
            'commentForm' => $form->createView(),
        ]);
    }
}
