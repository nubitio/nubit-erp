<?php

namespace App\MilitaryPatient\Application;

use App\Base\BaseController;
use App\MilitaryPatient\Domain\GetOneMilitaryPatientByCustomerIdUseCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MilitaryPatientController extends BaseController
{
    #[Route('/military-patient', name: 'military-patient_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('military-patient/index.html.twig');
    }

    #[Route('/military-patient/by-customer/{ruc}', name: 'military-patient_new', methods: ['GET', 'POST'])]
    public function getOneByCustomerId(string $ruc, GetOneMilitaryPatientByCustomerIdUseCase $getOneMilitaryPatientByCustomerIdUseCase): Response
    {
        $militaryPatient = $getOneMilitaryPatientByCustomerIdUseCase->__invoke($ruc);

        return $this->json($militaryPatient);
    }
}
