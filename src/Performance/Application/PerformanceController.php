<?php

namespace App\Performance\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PerformanceController extends BaseController
{
    #[Route('/performance', name: 'performance_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('performance/index.html.twig');
    }
}
