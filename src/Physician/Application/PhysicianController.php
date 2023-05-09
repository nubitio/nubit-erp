<?php

namespace App\Physician\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PhysicianController extends BaseController
{
    #[Route('/physician', name: 'physician_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('physician/index.html.twig');
    }
}
