<?php

namespace App\Ambiente\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AmbienteController extends BaseController
{
    #[Route('/ambiente', name: 'ambiente_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('ambiente/index.html.twig');
    }
}
