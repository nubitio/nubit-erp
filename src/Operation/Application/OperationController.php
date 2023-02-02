<?php

namespace App\Operation\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OperationController extends BaseController
{
    #[Route('/operation', name: 'operation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('operation/index.html.twig');
    }
}
