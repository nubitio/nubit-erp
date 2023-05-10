<?php

namespace App\ConsultationSale\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConsultationSaleController extends BaseController
{
    #[Route('/consultation-sale', name: 'consultation-sale_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('consultation-sale/index.html.twig');
    }
}
