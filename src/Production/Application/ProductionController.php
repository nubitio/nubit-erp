<?php

namespace App\Production\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductionController extends BaseController
{
    #[Route('/production', name: 'production_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('production/index.html.twig');
    }
}
