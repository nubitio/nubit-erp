<?php

namespace App\Specialty\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SpecialtyController extends BaseController
{
    #[Route('/specialty', name: 'specialty_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('specialty/index.html.twig');
    }
}
