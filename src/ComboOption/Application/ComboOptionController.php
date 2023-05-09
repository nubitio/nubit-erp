<?php

namespace App\ComboOption\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ComboOptionController extends BaseController
{
    #[Route('/combo-option', name: 'combo-option_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('combo-option/index.html.twig');
    }
}
