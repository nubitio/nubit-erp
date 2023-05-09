<?php

namespace App\PatientType\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PatientTypeController extends BaseController
{
    #[Route('/patient-type', name: 'patient-type_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('patient-type/index.html.twig');
    }
}
