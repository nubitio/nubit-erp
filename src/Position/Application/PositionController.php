<?php

namespace App\Position\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PositionController extends BaseController
{
    #[Route('/position', name: 'position_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('position/index.html.twig');
    }
}
