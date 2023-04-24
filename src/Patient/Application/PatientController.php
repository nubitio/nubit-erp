<?php

namespace App\Patient\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PatientController extends BaseController
{
    #[Route('/patient', name: 'patient_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('patient/index.html.twig');
    }
}
