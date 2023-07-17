<?php

namespace App\Orden\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrdenController extends BaseController
{
    #[Route('/orden', name: 'orden_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('orden/index.html.twig');
    }
}
