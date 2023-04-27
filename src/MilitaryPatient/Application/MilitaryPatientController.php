<?php

namespace App\MilitaryPatient\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MilitaryPatientController extends BaseController
{
    #[Route('/military-patient', name: 'military-patient_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('military-patient/index.html.twig');
    }
}
