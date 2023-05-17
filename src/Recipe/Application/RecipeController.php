<?php

namespace App\Recipe\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RecipeController extends BaseController
{
    #[Route('/recipe', name: 'recipe_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('recipe/index.html.twig');
    }
}
