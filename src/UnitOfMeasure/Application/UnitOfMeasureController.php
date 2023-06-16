<?php

namespace App\UnitOfMeasure\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UnitOfMeasureController extends BaseController
{
    #[Route('/unit-of-measure', name: 'unit-of-measure_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('unit-of-measure/index.html.twig');
    }
}
