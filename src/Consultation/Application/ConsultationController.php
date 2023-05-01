<?php

namespace App\Consultation\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConsultationController extends BaseController
{
    #[Route('/consultation', name: 'consultation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('consultation/index.html.twig');
    }
}
