<?php

namespace App\Mesa\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MesaController extends BaseController
{
    #[Route('/mesa', name: 'mesa_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('mesa/index.html.twig');
    }
}
