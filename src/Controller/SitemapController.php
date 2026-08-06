<?php

namespace App\Controller;

use App\Repository\RecipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitemapController extends AbstractController
{
    /**
     * Lists what a visitor without an account can reach. findVisibleFor(null)
     * returns exactly the published recipes of active owners, so deactivated
     * and admin-blocked ones stay out of the index.
     */
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'], methods: ['GET'])]
    public function index(RecipeRepository $recipeRepository): Response
    {
        $response = $this->render('sitemap.xml.twig', [
            'recipes' => $recipeRepository->findVisibleFor(null),
        ]);

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
